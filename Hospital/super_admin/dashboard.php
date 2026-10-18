<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
include "admin/connection.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

date_default_timezone_set('Africa/Nairobi');

// Get the logged-in administrator's ID
$admin_id = $_SESSION['user_id'] ?? null;

if (!$admin_id) {
    die("Administrator not logged in.");
}

// Fetch Administrator Information
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.department, u.role
        FROM users u
        WHERE u.id = ? AND u.role IN ('Super Admin', 'Admin')
    ");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        die("Administrator not found.");
    }
} catch (PDOException $e) {
    die("Error fetching administrator information: " . $e->getMessage());
}

// ====================== UPDATED STATISTICS SECTION ======================

$stats = [];
$current_month = date('Y-m');
$first_day_of_month = date('Y-m-01');
$thirty_days_ago = date('Y-m-d', strtotime('-30 days'));

// 1. Total Patients (Active)
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM patients WHERE status = 'Active'");
    $stats['total_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = CURDATE()");
    $stats['new_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['total_patients'] = 0;
    $stats['new_patients'] = 0;
}

// 2. Total Staff (Active)
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE status = 'active' AND role != 'Patient'");
    $stats['total_staff'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['total_staff'] = 0;
}

// 3. REVENUE STATISTICS - Using billing_payments table like accounts dashboard
// Today's Revenue
try {
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as today_revenue,
            COUNT(*) as payment_count
        FROM billing_payments 
        WHERE DATE(payment_date) = CURDATE()
        AND status = 'completed'
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['today_revenue'] = $result['today_revenue'] ?? 0;
    $stats['today_payments'] = $result['payment_count'] ?? 0;
} catch (PDOException $e) {
    $stats['today_revenue'] = 0;
    $stats['today_payments'] = 0;
}

// This Month's Revenue
try {
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(amount), 0) as month_revenue,
            COUNT(*) as payment_count
        FROM billing_payments 
        WHERE payment_date >= :first_day
        AND status = 'completed'
    ");
    $stmt->execute([':first_day' => $first_day_of_month]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['month_revenue'] = $result['month_revenue'] ?? 0;
    $stats['month_payments'] = $result['payment_count'] ?? 0;
} catch (PDOException $e) {
    $stats['month_revenue'] = 0;
    $stats['month_payments'] = 0;
}

// Total Revenue (All time)
try {
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(amount), 0) as total_revenue,
            COUNT(*) as payment_count
        FROM billing_payments 
        WHERE status = 'completed'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_revenue'] = $result['total_revenue'] ?? 0;
    $stats['total_payments'] = $result['payment_count'] ?? 0;
} catch (PDOException $e) {
    $stats['total_revenue'] = 0;
    $stats['total_payments'] = 0;
}

// Pending/Failed Payments
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as count,
            COALESCE(SUM(amount), 0) as total
        FROM billing_payments 
        WHERE status = 'failed'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['failed_payments'] = $result['count'] ?? 0;
    $stats['failed_amount'] = $result['total'] ?? 0;
} catch (PDOException $e) {
    $stats['failed_payments'] = 0;
    $stats['failed_amount'] = 0;
}

// Average Transaction Value
$stats['avg_transaction_value'] = $stats['total_payments'] > 0 
    ? $stats['total_revenue'] / $stats['total_payments'] 
    : 0;

// 4. Bed Occupancy Rate
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_beds,
            SUM(CASE WHEN bed_status = 'occupied' THEN 1 ELSE 0 END) as occupied_beds
        FROM beds 
        WHERE bed_status IN ('available', 'occupied')
    ");
    $bed_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['bed_occupancy_rate'] = $bed_data['total_beds'] > 0 ? 
        round(($bed_data['occupied_beds'] / $bed_data['total_beds']) * 100, 1) : 0;
    $stats['available_beds'] = $bed_data['total_beds'] - $bed_data['occupied_beds'];
} catch (PDOException $e) {
    $stats['bed_occupancy_rate'] = 0;
    $stats['available_beds'] = 0;
}

// 5. Pending System Tasks
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointment_requests WHERE status = 'pending'");
    $pending_requests = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM lab_orders WHERE status IN ('ordered', 'in_progress')");
    $pending_labs = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM billing WHERE status IN ('pending', 'partial')");
    $pending_bills = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stats['pending_tasks'] = $pending_requests + $pending_labs + $pending_bills;
} catch (PDOException $e) {
    $stats['pending_tasks'] = 0;
}

// 6. SOS Emergency Alerts
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM sos_alerts 
        WHERE status = 'active' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stats['active_sos'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM sos_alerts 
        WHERE status = 'active' 
        AND priority IN ('critical', 'high')
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stats['urgent_sos'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['active_sos'] = 0;
    $stats['urgent_sos'] = 0;
}

// 7. Today's Appointments
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE DATE(appointment_date) = CURDATE()
    ");
    $stats['today_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['today_appointments'] = 0;
}

// ====================== SERVICE TYPE BREAKDOWN (TODAY) ======================
$service_types = ['consultation' => 0, 'pharmacy' => 0, 'lab' => 0, 'admission' => 0, 'consolidated' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT 
            service_type,
            COUNT(*) as transaction_count,
            COALESCE(SUM(amount), 0) as revenue
        FROM billing_payments 
        WHERE DATE(payment_date) = CURDATE()
        AND status = 'completed'
        GROUP BY service_type
        ORDER BY revenue DESC
    ");
    $stmt->execute();
    $today_service_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($today_service_revenue as $service) {
        if (isset($service_types[$service['service_type']])) {
            $service_types[$service['service_type']] = $service['revenue'];
        }
    }
} catch (PDOException $e) {
    // Error handled by default values
}

// ====================== SOS ALERTS DETAILS ======================
$sos_alerts = [];
try {
    $stmt = $pdo->query("
        SELECT 
            s.id,
            s.patient_id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            COALESCE(p.address, 'Location Not Set') as room_number,
            s.latitude,
            s.longitude,
            s.emergency_type,
            s.priority,
            s.status,
            s.created_at,
            TIMESTAMPDIFF(MINUTE, s.created_at, NOW()) as minutes_ago,
            COALESCE(u.department, 'Unassigned') as assigned_dept
        FROM sos_alerts s
        LEFT JOIN patients p ON s.patient_id = p.id
        LEFT JOIN users u ON s.assigned_to = u.id
        WHERE s.status = 'active'
        ORDER BY 
            CASE s.priority 
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
            END,
            s.created_at ASC
        LIMIT 10
    ");
    
    $sos_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sos_alerts = [];
}

// ====================== UPDATED DEPARTMENT PERFORMANCE ======================
$department_performance = [];
try {
    $stmt = $pdo->query("
        SELECT 
            d.name as department,
            COUNT(DISTINCT u.id) as staff_count,
            COUNT(DISTINCT a.id) as appointment_count,
            COALESCE(SUM(p.amount), 0) as revenue
        FROM departments d
        LEFT JOIN users u ON d.name = u.department AND u.status = 'active'
        LEFT JOIN appointments a ON u.id = a.doctor_id 
            AND MONTH(a.appointment_date) = MONTH(CURDATE())
            AND YEAR(a.appointment_date) = YEAR(CURDATE())
            AND a.status = 'completed'
        LEFT JOIN billing_payments p ON a.id = p.appointment_id 
            AND p.service_type = 'consultation'
            AND p.status = 'completed'
        GROUP BY d.id, d.name
        ORDER BY revenue DESC
        LIMIT 6
    ");
    $department_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $department_performance = [];
}

// ====================== UPDATED RECENT SYSTEM ACTIVITIES ======================
$system_activities = [];
try {
    // Combine multiple activity sources
    $activity_queries = [];
    
    // Appointments
    $activity_queries[] = "
        SELECT 
            'appointment' as type,
            CONCAT('Appointment Booked: ', a.appointment_number) as description,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            a.created_at as activity_date
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY a.created_at DESC
        LIMIT 5
    ";
    
    // Lab Orders
    $activity_queries[] = "
        SELECT 
            'lab' as type,
            CONCAT('Lab Order: ', lo.order_number) as description,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            lo.order_date as activity_date
        FROM lab_orders lo
        JOIN patients p ON lo.patient_id = p.id
        WHERE lo.order_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY lo.order_date DESC
        LIMIT 5
    ";
    
    // New Patients
    $activity_queries[] = "
        SELECT 
            'patient' as type,
            CONCAT('New Patient Registered: ', p.first_name, ' ', p.last_name) as description,
            p.patient_id as patient_name,
            p.created_at as activity_date
        FROM patients p
        WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY p.created_at DESC
        LIMIT 5
    ";
    
    // Payments
    $activity_queries[] = "
        SELECT 
            'payment' as type,
            CONCAT('Payment Received: ', bp.receipt_number) as description,
            bp.patient_name,
            bp.payment_date as activity_date
        FROM billing_payments bp
        WHERE bp.payment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND bp.status = 'completed'
        ORDER BY bp.payment_date DESC
        LIMIT 5
    ";
    
    // Execute each query and combine results
    foreach ($activity_queries as $query) {
        $stmt = $pdo->query($query);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $system_activities = array_merge($system_activities, $results);
    }
    
    // Sort all activities by date and limit to 8
    usort($system_activities, function($a, $b) {
        return strtotime($b['activity_date']) - strtotime($a['activity_date']);
    });
    
    $system_activities = array_slice($system_activities, 0, 8);
    
} catch (PDOException $e) {
    $system_activities = [];
}

// ====================== RECENT TRANSACTIONS ======================
$recent_transactions = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            receipt_number,
            patient_name,
            service_type,
            amount,
            payment_method,
            status,
            payment_date
        FROM billing_payments 
        WHERE status IN ('completed', 'failed')
        ORDER BY payment_date DESC
        LIMIT 8
    ");
    $stmt->execute();
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_transactions = [];
}

// ====================== STAFF PERFORMANCE OVERVIEW ======================
$staff_performance = [];
try {
    $stmt = $pdo->query("
        SELECT 
            u.id,
            CONCAT(u.first_name, ' ', u.last_name) as staff_name,
            u.role,
            u.department,
            COUNT(DISTINCT a.id) as appointment_count,
            COUNT(DISTINCT p.id) as prescription_count,
            COALESCE(SUM(bp.amount), 0) as revenue_generated
        FROM users u
        LEFT JOIN appointments a ON u.id = a.doctor_id 
            AND MONTH(a.appointment_date) = MONTH(CURDATE())
            AND a.status = 'completed'
        LEFT JOIN billing_payments bp ON a.id = bp.appointment_id 
            AND bp.service_type = 'consultation'
            AND bp.status = 'completed'
        LEFT JOIN prescriptions p ON u.id = p.doctor_id 
            AND MONTH(p.prescription_date) = MONTH(CURDATE())
        WHERE u.role IN ('Doctor', 'Nurse')
        AND u.status = 'active'
        GROUP BY u.id, u.first_name, u.last_name, u.role, u.department
        ORDER BY revenue_generated DESC
        LIMIT 6
    ");
    $staff_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $staff_performance = [];
}

// Display date and time
$display_date = date('F j, Y');
$display_time = date('h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Dashboard | AuraCare HMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #198dec;
            --primary-light: #35bfff;
            --primary-dark: #0a0281;
            --secondary: #14b8a6;
            --accent: #22d3ee;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --purple: #8b5cf6;
            --teal: #0d9488;
            --light: #f0fdfa;
            --dark: #134e4a;
            --gray: #64748b;
            --light-gray: #e2e8f0;
            --card-bg: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.6;
            font-weight: bold;
            font-size: 18px;
        }

        .dashboard-container {
            padding: 20px;
            margin-left: 300px;
            transition: all 0.3s;
            min-height: 100vh;
            gap: 20px;
        }

        @media (max-width: 1200px) {
            .dashboard-container {
                margin-left: 0;
                padding: 15px;
            }
        }

        /* Header Styles */
        .dashboard-header {
            margin-bottom: 30px;
            background: var(--card-bg);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-left: 5px solid var(--primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .admin-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(8, 145, 178, 0.3);
        }

        .admin-details h1 {
            font-size: 28px;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .admin-details p {
            color: var(--gray);
            font-size: 16px;
            font-weight: 500;
        }

        .header-time {
            text-align: right;
        }

        .header-time .date {
            font-size: 20px;
            color: var(--dark);
            font-weight: 700;
            margin-bottom: 5px;
        }

        .header-time .time {
            font-size: 16px;
            color: var(--gray);
            font-weight: 500;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        .stat-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            transition: all 0.3s;
            cursor: pointer;
            border-top: 4px solid var(--primary);
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 160px;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .stat-card-primary { border-top-color: var(--primary); }
        .stat-card-success { border-top-color: var(--success); }
        .stat-card-warning { border-top-color: var(--warning); }
        .stat-card-danger { border-top-color: var(--danger); }
        .stat-card-info { border-top-color: var(--accent); }
        .stat-card-purple { border-top-color: var(--purple); }
        .stat-card-teal { border-top-color: var(--teal); }

        .sos-stat-card {
            animation: sosPulse 2s infinite;
            border-left: 5px solid var(--danger);
        }

        @keyframes sosPulse {
            0% { box-shadow: 0 2px 8px rgba(244, 67, 54, 0.3); }
            50% { box-shadow: 0 2px 15px rgba(244, 67, 54, 0.6); }
            100% { box-shadow: 0 2px 8px rgba(244, 67, 54, 0.3); }
        }

        .stat-content {
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stat-text h3 {
            font-size: 14px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .stat-value {
            font-size: clamp(28px, 4vw, 36px);
            font-weight: 800;
            color: var(--dark);
            line-height: 1;
        }

        .stat-value small {
            font-size: 18px;
            font-weight: 600;
            color: var(--gray);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            flex-shrink: 0;
        }

        .stat-card-primary .stat-icon { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); }
        .stat-card-success .stat-icon { background: linear-gradient(135deg, var(--success) 0%, #059669 100%); }
        .stat-card-warning .stat-icon { background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%); }
        .stat-card-danger .stat-icon { background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%); }
        .stat-card-info .stat-icon { background: linear-gradient(135deg, var(--accent) 0%, var(--primary-light) 100%); }
        .stat-card-purple .stat-icon { background: linear-gradient(135deg, var(--purple) 0%, #7c3aed 100%); }
        .stat-card-teal .stat-icon { background: linear-gradient(135deg, var(--teal) 0%, #0f766e 100%); }

        .stat-footer {
            padding-top: 15px;
            border-top: 1px solid var(--light-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            color: var(--gray);
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 600;
        }

        .stat-trend.up { color: var(--success); }
        .stat-trend.down { color: var(--danger); }

        /* Service Type Breakdown */
        .service-breakdown {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .service-card {
            background: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-align: center;
            transition: all 0.3s;
        }

        .service-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .service-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 15px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .service-card.consultation .service-icon { background: var(--primary); }
        .service-card.pharmacy .service-icon { background: var(--success); }
        .service-card.lab .service-icon { background: var(--warning); }
        .service-card.admission .service-icon { background: var(--purple); }
        .service-card.consolidated .service-icon { background: var(--teal); }

        .service-card h3 {
            font-size: 16px;
            color: var(--dark);
            margin-bottom: 8px;
            font-weight: 600;
        }

        .service-card .amount {
            font-size: 20px;
            font-weight: 700;
            color: var(--dark);
        }

        /* Content Sections */
        .content-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 500px), 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .section-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 100%;
            border: 1px solid var(--light-gray);
        }

        .section-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header h2 {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header .badge {
            background: rgba(255,255,255,0.2);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .section-content {
            flex: 1;
            padding: 0;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .table-responsive {
            flex: 1;
            overflow-y: auto;
            max-height: 400px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        th {
            background: var(--light);
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--light-gray);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--light-gray);
            font-size: 13px;
            vertical-align: middle;
        }

        tbody tr {
            transition: background-color 0.2s;
            cursor: pointer;
        }

        tbody tr:hover {
            background: var(--light);
        }

        /* Status Badges */
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .status-completed { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .status-failed { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .status-refunded { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .status-pending { background: rgba(148, 163, 184, 0.1); color: var(--gray); }

        .payment-method-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            background: rgba(8, 145, 178, 0.1);
            color: var(--primary);
        }

        .service-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .service-consultation { background: rgba(8, 145, 178, 0.1); color: var(--primary); }
        .service-pharmacy { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .service-lab { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .service-admission { background: rgba(139, 92, 246, 0.1); color: var(--purple); }
        .service-consolidated { background: rgba(13, 148, 136, 0.1); color: var(--teal); }

        /* SOS Portal */
        .sos-portal {
            background: linear-gradient(135deg, #f72585 0%, #b5179e 100%);
            color: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(247, 37, 133, 0.3);
            position: relative;
            overflow: hidden;
        }

     .sos-portal::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
    background-size: 20px 20px;
    /* Animation removed from default - will be added via inline style */
}
        @keyframes sosPulse {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .sos-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
        }

        .sos-title {
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .sos-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 18px;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .sos-alerts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
        }

        .sos-alert-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            color: #333;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .sos-alert-card:hover {
            transform: translateY(-3px);
        }

        .sos-alert-card.high {
            border-left: 5px solid #f44336;
        }

        .sos-alert-card.medium {
            border-left: 5px solid #ff9800;
        }

        .sos-alert-card.low {
            border-left: 5px solid #4CAF50;
        }

        .alert-priority {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .priority-high { background: #ffeaea; color: #d00000; }
        .priority-medium { background: #fff3cd; color: #856404; }
        .priority-low { background: #d1ecf1; color: #0c5460; }

        .alert-time {
            font-size: 12px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
        }

        .sos-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 1;
        }

        .sos-btn {
            background: white;
            color: #f44336;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-decoration: none;
        }

        .sos-btn:hover {
            background: #f44336;
            color: white;
            transform: translateY(-2px);
        }

        /* View All Link */
        .view-all-link {
            display: block;
            padding: 15px;
            text-align: center;
            background: var(--light);
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            border-top: 1px solid var(--light-gray);
        }

        .view-all-link:hover {
            background: var(--primary);
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--light-gray);
            margin-bottom: 15px;
        }

        .empty-state h4 {
            font-size: 16px;
            margin-bottom: 8px;
            color: var(--secondary);
        }

        .empty-state p {
            font-size: 13px;
            margin: 0;
        }

        /* Refresh Button */
        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            font-size: 20px;
            transition: all 0.3s;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .refresh-btn:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }
            
            .dashboard-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .admin-info {
                flex-direction: column;
                text-align: center;
            }
            
            .service-breakdown {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            
            .content-sections {
                grid-template-columns: 1fr;
            }
            
            .refresh-btn {
                width: 50px;
                height: 50px;
                bottom: 20px;
                right: 20px;
            }
            
            .sos-actions {
                flex-direction: column;
            }
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            border: 1px solid transparent;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            border-color: var(--primary);
        }

        .action-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        }

        .action-content h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .action-content p {
            font-size: 14px;
            color: var(--gray);
            margin: 0;
        }

        .activity-type-icon {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: white;
            margin-right: 10px;
        }

        .activity-appointment { background: var(--primary); }
        .activity-patient { background: var(--success); }
        .activity-lab { background: var(--warning); }
        .activity-payment { background: var(--purple); }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="admin-info">
                <div class="admin-avatar">
                    <?php 
                        $initials = substr($admin['first_name'], 0, 1) . substr($admin['last_name'], 0, 1);
                        echo strtoupper($initials);
                    ?>
                </div>
                <div class="admin-details">
                    <h1><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></h1>
                    <p><?php echo htmlspecialchars($admin['role']); ?> • <?php echo htmlspecialchars($admin['department']); ?></p>
                </div>
            </div>
            <div class="header-time">
                <div class="date"><?php echo $display_date; ?></div>
                <div class="time"><?php echo $display_time; ?></div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <!-- Total Patients -->
            <div class="stat-card stat-card-primary" onclick="window.location.href='add_new_patients.php'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Total Patients</h3>
                        <div class="stat-value"><?php echo number_format($stats['total_patients']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span><?php echo number_format($stats['new_patients']); ?> new today</span>
                    <div class="stat-trend up">
                        <i class="fas fa-user-plus"></i>
                    </div>
                </div>
            </div>

            <!-- Total Staff -->
            <div class="stat-card stat-card-success" onclick="window.location.href='manage_users.php'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Total Staff</h3>
                        <div class="stat-value"><?php echo number_format($stats['total_staff']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span>Active employees</span>
                    <div class="stat-trend up">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <!-- Today's Revenue -->
            <div class="stat-card stat-card-info" onclick="window.location.href='reports.php?report=financial'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Today's Revenue</h3>
                        <div class="stat-value">Ksh <?php echo number_format($stats['today_revenue'], 0); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span><?php echo number_format($stats['today_payments']); ?> payments</span>
                    <div class="stat-trend up">
                        <i class="fas fa-arrow-up"></i>
                        <span>Today</span>
                    </div>
                </div>
            </div>

            <!-- Monthly Revenue -->
            <div class="stat-card stat-card-purple" onclick="window.location.href='reports.php?report=revenue'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Monthly Revenue</h3>
                        <div class="stat-value">Ksh <?php echo number_format($stats['month_revenue'], 0); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span><?php echo number_format($stats['month_payments']); ?> payments</span>
                    <div class="stat-trend up">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Current month</span>
                    </div>
                </div>
            </div>

            <!-- SOS Alerts -->
         <!-- SOS Alerts -->
<div class="stat-card stat-card-danger <?php echo $stats['urgent_sos'] > 0 ? 'sos-stat-card' : ''; ?>" onclick="window.location.href='sos_portal.php'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Urgent SOS Alerts</h3>
                        <div class="stat-value"><?php echo number_format($stats['urgent_sos']); ?></div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-first-aid"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span><?php echo number_format($stats['active_sos']); ?> active alerts</span>
                    <div class="stat-trend down">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Urgent</span>
                    </div>
                </div>
            </div>

            <!-- Bed Occupancy -->
            <!-- <div class="stat-card stat-card-teal" onclick="window.location.href='beds.php'">
                <div class="stat-content">
                    <div class="stat-text">
                        <h3>Bed Occupancy</h3>
                        <div class="stat-value"><?php echo number_format($stats['bed_occupancy_rate'], 1); ?>%</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-bed"></i>
                    </div>
                </div>
                <div class="stat-footer">
                    <span><?php echo number_format($stats['available_beds']); ?> beds available</span>
                    <div class="stat-trend <?php echo $stats['bed_occupancy_rate'] > 80 ? 'down' : 'up'; ?>">
                        <?php if ($stats['bed_occupancy_rate'] > 80): ?>
                            <i class="fas fa-exclamation-circle"></i>
                            <span>High</span>
                        <?php else: ?>
                            <i class="fas fa-check-circle"></i>
                            <span>Normal</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div> -->
        </div>

        <!-- Service Type Breakdown -->
        <div class="service-breakdown">
            <div class="service-card consultation">
                <div class="service-icon">
                    <i class="fas fa-user-md"></i>
                </div>
                <h3>Consultation</h3>
                <div class="amount">Ksh <?php echo number_format($service_types['consultation'], 0); ?></div>
            </div>
            
            <div class="service-card pharmacy">
                <div class="service-icon">
                    <i class="fas fa-pills"></i>
                </div>
                <h3>Pharmacy</h3>
                <div class="amount">Ksh <?php echo number_format($service_types['pharmacy'], 0); ?></div>
            </div>
            
            <div class="service-card lab">
                <div class="service-icon">
                    <i class="fas fa-flask"></i>
                </div>
                <h3>Laboratory</h3>
                <div class="amount">Ksh <?php echo number_format($service_types['lab'], 0); ?></div>
            </div>
            
            <div class="service-card admission">
                <div class="service-icon">
                    <i class="fas fa-procedures"></i>
                </div>
                <h3>Admission</h3>
                <div class="amount">Ksh <?php echo number_format($service_types['admission'], 0); ?></div>
            </div>
<!--             
            <div class="service-card consolidated">
                <div class="service-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <h3>Consolidated</h3>
                <div class="amount">Ksh <?php echo number_format($service_types['consolidated'], 0); ?></div>
            </div> -->
        </div>

        <!-- SOS Emergency Portal -->
       <div class="sos-portal">
            <div class="sos-header">
                <div class="sos-title">
                    <i class="fas fa-first-aid fa-2x"></i>
                    <span>Emergency SOS Portal</span>
                </div>
                <div class="sos-badge">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <?php echo $stats['active_sos']; ?> Active Alerts
                </div>
            </div>

            <?php if (count($sos_alerts) > 0): ?>
                <div class="sos-alerts-grid">
                    <?php foreach ($sos_alerts as $alert): 
                        $priority_class = strtolower($alert['priority']);
                        $time_ago = $alert['minutes_ago'] < 60 ? 
                            $alert['minutes_ago'] . ' min ago' : 
                            floor($alert['minutes_ago'] / 60) . ' hour(s) ago';
                    ?>
                        <div class="sos-alert-card <?php echo $priority_class; ?>">
                            <div class="alert-priority priority-<?php echo $priority_class; ?>">
                                <i class="fas fa-exclamation-circle"></i> 
                                <?php echo ucfirst($alert['priority']); ?> Priority
                            </div>
                            <h4 style="margin-bottom: 10px;">
                                <i class="fas fa-user-injured"></i> 
                                <?php echo htmlspecialchars($alert['patient_name']); ?>
                            </h4>
                            <p style="margin-bottom: 8px;">
                                <strong>Emergency:</strong> 
                                <?php echo htmlspecialchars($alert['emergency_type']); ?>
                            </p>
                            <p style="margin-bottom: 8px;" id="location-<?php echo $alert['id']; ?>">
                                <strong>Location:</strong> 
                                <?php echo htmlspecialchars($alert['room_number']); ?>
                            </p>
                            <?php if ($alert['assigned_dept'] && $alert['assigned_dept'] != 'Unassigned'): ?>
                                <p><strong>Assigned to:</strong> <?php echo htmlspecialchars($alert['assigned_dept']); ?></p>
                            <?php endif; ?>
                            <div class="alert-time">
                                <i class="far fa-clock"></i> Alert received <?php echo $time_ago; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px 20px;">
                    <i class="fas fa-check-circle fa-3x" style="color: rgba(255,255,255,0.7); margin-bottom: 20px;"></i>
                    <h3 style="margin-bottom: 10px;">No Active Emergencies</h3>
                    <p style="opacity: 0.9;">All Patients are currently stable</p>
                </div>
            <?php endif; ?>

            <div class="sos-actions">
                <a href="sos_portal.php" class="sos-btn">
                    <i class="fas fa-tachometer-alt"></i> View All Alerts
                </a>
            </div>
        </div>

        <!-- Main Content Section -->
        <div class="content-sections">
                        <!-- Quick Actions -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                    <span class="badge">System Tools</span>
                </div>
                <div class="section-content">
                    <div style="padding: 20px;">
                        <div class="quick-actions">
                            <a href="sos_portal.php" class="action-card">
                                <div class="action-icon">
                                    <i class="fas fa-first-aid"></i>
                                </div>
                                <div class="action-content">
                                    <h3>SOS Portal</h3>
                                    <p>Emergency management</p>
                                </div>
                            </a>
                            
                            <a href="settings.php" class="action-card">
                                <div class="action-icon" style="background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);">
                                    <i class="fas fa-cog"></i>
                                </div>
                                <div class="action-content">
                                    <h3>System Settings</h3>
                                    <p>Change Account Settings</p>
                                </div>
                            </a>
                            
                            <a href="reports.php" class="action-card">
                                <div class="action-icon" style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%);">
                                    <i class="fas fa-chart-bar"></i>
                                </div>
                                <div class="action-content">
                                    <h3>Generate Reports</h3>
                                    <p>Financial analytics</p>
                                </div>
                            </a>
                            
                            <a href="manage_users.php" class="action-card">
                                <div class="action-icon" style="background: linear-gradient(135deg, var(--purple) 0%, #7c3aed 100%);">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <div class="action-content">
                                    <h3>Manage Employees</h3>
                                    <p>Staff management</p>
                                </div>
                            </a>
                            
                            <a href="add_new_patients.php" class="action-card">
                                <div class="action-icon" style="background: linear-gradient(135deg, var(--teal) 0%, #0f766e 100%);">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <div class="action-content">
                                    <h3>Register Patient</h3>
                                    <p>New patient registration</p>
                                </div>
                            </a>
                            
                            <a href="wards.php" class="action-card">
                                <div class="action-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                                    <i class="fas fa-bed"></i>
                                </div>
                                <div class="action-content">
                                    <h3>Bed Management</h3>
                                    <p>Ward & bed allocation</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-exchange-alt"></i> Recent Transactions</h2>
                    <span class="badge"><?php echo count($recent_transactions); ?> transactions</span>
                </div>
                <div class="section-content">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Receipt No</th>
                                    <th>Patient</th>
                                    <th>Service</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_transactions) > 0): ?>
                                    <?php foreach ($recent_transactions as $tx): 
                                        $service_class = 'service-' . $tx['service_type'];
                                        $status_class = 'status-' . $tx['status'];
                                    ?>
                                        <tr onclick="window.location.href='view_payment.php?id=<?php echo $tx['id']; ?>'">
                                            <td>
                                                <strong><?php echo htmlspecialchars(substr($tx['receipt_number'], 0, 10)); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($tx['patient_name']); ?></td>
                                            <td>
                                                <span class="service-badge <?php echo $service_class; ?>">
                                                    <?php echo ucfirst($tx['service_type']); ?>
                                                </span>
                                            </td>
                                            <td><strong>Ksh <?php echo number_format($tx['amount'], 2); ?></strong></td>
                                            <td>
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <?php echo ucfirst($tx['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="empty-state">
                                            <i class="fas fa-exchange-alt"></i>
                                            <h4>No Recent Transactions</h4>
                                            <p>No billing payments recorded yet</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="billing_module.php" class="view-all-link">
                        View All Transactions <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!--  Recent Activities Section -->
        <div class="content-sections">

            <!-- Recent System Activities -->
            <div class="section-card">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> Recent System Activities</h2>
                    <span class="badge">Last 7 days</span>
                </div>
                <div class="section-content">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Activity</th>
                                    <th>Patient</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($system_activities) > 0): ?>
                                    <?php foreach ($system_activities as $activity): 
                                        $type_class = 'activity-' . $activity['type'];
                                        $icon_map = [
                                            'appointment' => 'fa-calendar-check',
                                            'patient' => 'fa-user-plus',
                                            'lab' => 'fa-flask',
                                            'payment' => 'fa-money-bill-wave'
                                        ];
                                        $icon = $icon_map[$activity['type']] ?? 'fa-circle';
                                    ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center;">
                                                    <div class="activity-type-icon <?php echo $type_class; ?>">
                                                        <i class="fas <?php echo $icon; ?>"></i>
                                                    </div>
                                                    <span><?php echo htmlspecialchars($activity['description']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($activity['patient_name']); ?></td>
                                            <td><?php echo date('H:i', strtotime($activity['activity_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="empty-state">
                                            <i class="fas fa-history"></i>
                                            <h4>No Recent Activities</h4>
                                            <p>No system activities recorded</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Refresh Button -->
    <button class="refresh-btn" onclick="refreshDashboard()" title="Refresh Dashboard">
        <i class="fas fa-sync-alt"></i>
    </button>

    <script>
        // Refresh dashboard function
        function refreshDashboard() {
            const btn = document.querySelector('.refresh-btn');
            const container = document.querySelector('.dashboard-container');
            
            // Add loading state
            btn.classList.add('pulse');
            container.style.opacity = '0.7';
            
            // Refresh after 1 second
            setTimeout(() => {
                location.reload();
            }, 1000);
        }

        // Auto-refresh every 2 minutes
        setTimeout(function() {
            refreshDashboard();
        }, 120000);

        // Auto-refresh SOS alerts every 30 seconds
        setInterval(() => {
            fetch('api/check_sos_alerts.php')
                .then(response => response.json())
                .then(data => {
                    if (data.newAlerts > 0 && data.highPriority > 0) {
                        showNotification(`🚨 ${data.newAlerts} new emergency alert(s)`, 'error');
                        setTimeout(() => location.reload(), 2000);
                    }
                })
                .catch(error => console.error('Error checking SOS alerts:', error));
        }, 30000);

        // Show Notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#10b981' : '#ef4444'};
                color: white;
                padding: 15px 25px;
                border-radius: 10px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                z-index: 10000;
                animation: slideIn 0.3s ease;
                font-weight: 500;
            `;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                ${message}
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Add CSS for notifications
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + R to refresh
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                refreshDashboard();
            }
            // Ctrl + S for SOS portal
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                window.location.href = 'sos_portal.php';
            }
            // Ctrl + D for dashboard
            if (e.ctrlKey && e.key === 'd') {
                e.preventDefault();
                window.location.href = 'index.php';
            }
        });

        // Make table rows clickable
        document.querySelectorAll('tbody tr').forEach(row => {
            if (!row.querySelector('.empty-state')) {
                row.style.cursor = 'pointer';
            }
        });
    </script>
</body>
</html>