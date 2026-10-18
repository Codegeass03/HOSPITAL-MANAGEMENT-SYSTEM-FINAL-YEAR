<?php
session_start();
include "includes/db_connect.php";


if (!isset($pdo)) {
    die("Database connection failed.");
}

// Check if user is a lab technician
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Lab Technician') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$current_time = date('Y-m-d H:i:s');
date_default_timezone_set('Africa/Nairobi');

// Fetch Lab Technician Dashboard Statistics
$stats = [];

// 1. Tests Awaiting Collection (status = 'ordered')
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM lab_orders 
        WHERE status = 'ordered'
    ");
    $stats['awaiting_collection'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['awaiting_collection'] = 0;
}

// 2. Tests Awaiting Testing (status = 'in_progress' AND collected_by IS NOT NULL)
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM lab_orders 
        WHERE status = 'in_progress'
        AND collected_by IS NOT NULL
    ");
    $stats['awaiting_testing'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['awaiting_testing'] = 0;
}

// 3. Today's Completed Tests (by current technician)
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM lab_orders 
        WHERE (tested_by = ? OR collected_by = ?)
        AND DATE(completed_at) = CURDATE()
        AND status = 'completed'
    ");
    $stmt->execute([$user_id, $user_id]);
    $stats['today_completed'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['today_completed'] = 0;
}

// 4. Urgent/Stat Priority Tests
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM lab_orders 
        WHERE priority IN ('urgent', 'stat') 
        AND status IN ('ordered', 'in_progress')
    ");
    $stats['urgent_tests'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['urgent_tests'] = 0;
}

// 5. Tests Pending Verification
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM lab_orders 
        WHERE status = 'completed'
        AND verified_by IS NULL
        AND tested_by IS NOT NULL
    ");
    $stats['pending_verification'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['pending_verification'] = 0;
}

// 6. Overdue Tests (> 2 hours since order)
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM lab_orders 
        WHERE status IN ('ordered', 'in_progress')
        AND TIMESTAMPDIFF(HOUR, order_date, NOW()) > 2
    ");
    $stats['overdue_tests'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['overdue_tests'] = 0;
}

// Technician's Recent Activity (last 5 tests processed)
$recent_activity = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            lo.id,
            lo.order_number,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            lo.priority,
            lo.status,
            CASE 
                WHEN lo.tested_by = ? THEN 'tested'
                WHEN lo.collected_by = ? THEN 'collected'
                ELSE 'other'
            END as action,
            GREATEST(lo.tested_at, lo.collected_at, lo.completed_at) as action_time
        FROM lab_orders lo
        JOIN patients p ON lo.patient_id = p.id
        WHERE (lo.tested_by = ? OR lo.collected_by = ?)
        AND lo.status = 'completed'
        ORDER BY action_time DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_activity = [];
}

// Pending Collection Orders (for technician to collect)
$pending_collection = [];
try {
    $stmt = $pdo->query("
        SELECT 
            lo.id,
            lo.order_number,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id,
            lo.priority,
            lo.clinical_notes,
            TIMESTAMPDIFF(MINUTE, lo.order_date, NOW()) as minutes_pending,
            DATE_FORMAT(lo.order_date, '%H:%i') as order_time
        FROM lab_orders lo
        JOIN patients p ON lo.patient_id = p.id
        WHERE lo.status = 'ordered'
        ORDER BY 
            CASE lo.priority
                WHEN 'stat' THEN 1
                WHEN 'urgent' THEN 2
                ELSE 3
            END,
            lo.order_date ASC
        LIMIT 8
    ");
    $pending_collection = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pending_collection = [];
}

// Pending Testing Orders (collected but not tested)
$pending_testing = [];
try {
    $stmt = $pdo->query("
        SELECT 
            lo.id,
            lo.order_number,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id,
            lo.priority,
            lo.clinical_notes,
            TIMESTAMPDIFF(MINUTE, lo.collected_at, NOW()) as minutes_waiting,
            DATE_FORMAT(lo.collected_at, '%H:%i') as collected_time,
            CONCAT(uc.first_name, ' ', uc.last_name) as collected_by_name
        FROM lab_orders lo
        JOIN patients p ON lo.patient_id = p.id
        LEFT JOIN users uc ON lo.collected_by = uc.id
        WHERE lo.status = 'in_progress'
        AND lo.collected_by IS NOT NULL
        AND lo.tested_by IS NULL
        ORDER BY 
            CASE lo.priority
                WHEN 'stat' THEN 1
                WHEN 'urgent' THEN 2
                ELSE 3
            END,
            lo.collected_at ASC
        LIMIT 8
    ");
    $pending_testing = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pending_testing = [];
}

// Unpaid Lab Orders (from billing_payments table)
$unpaid_orders = [];
try {
    $stmt = $pdo->query("
        SELECT DISTINCT
            lo.id,
            lo.order_number,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            lo.total_amount,
            lo.paid_amount,
            (lo.total_amount - lo.paid_amount) as balance,
            lo.payment_status
        FROM lab_orders lo
        JOIN patients p ON lo.patient_id = p.id
        WHERE lo.payment_status IN ('unpaid', 'partial')
        AND lo.status = 'completed'
        ORDER BY lo.order_date DESC
        LIMIT 6
    ");
    $unpaid_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $unpaid_orders = [];
}

// Technician Performance Stats (this month)
$performance_stats = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN tested_by = ? THEN lo.id END) as tests_performed,
            COUNT(DISTINCT CASE WHEN collected_by = ? THEN lo.id END) as samples_collected,
            AVG(TIMESTAMPDIFF(MINUTE, lo.collected_at, lo.tested_at)) as avg_test_time_minutes,
            MIN(TIMESTAMPDIFF(MINUTE, lo.collected_at, lo.tested_at)) as fastest_test_minutes
        FROM lab_orders lo
        WHERE MONTH(lo.completed_at) = MONTH(CURDATE())
        AND YEAR(lo.completed_at) = YEAR(CURDATE())
        AND lo.status = 'completed'
    ");
    $stmt->execute([$user_id, $user_id]);
    $performance_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $performance_stats = [];
}

// Equipment Status (placeholder - in real app would come from equipment table)
$equipment_status = [
    ['name' => 'Centrifuge', 'status' => 'operational', 'last_maintenance' => '2026-01-15'],
    ['name' => 'Microscope', 'status' => 'operational', 'last_maintenance' => '2026-01-10'],
    ['name' => 'Hematology Analyzer', 'status' => 'maintenance', 'last_maintenance' => '2026-01-18'],
    ['name' => 'Chemistry Analyzer', 'status' => 'operational', 'last_maintenance' => '2026-01-12'],
];
include "includes/sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Technician Dashboard - AuraCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .main-content {
            margin-left: 300px;
            padding: 2rem;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            min-height: 100vh;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .page-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0;
            color: #1e293b;
            font-size: 1.75rem;
            font-weight: 700;
        }
        
        .page-subtitle {
            color: #64748b;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        .tech-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: #f1f5f9;
            padding: 0.75rem 1.25rem;
            border-radius: 0.75rem;
        }
        
        .tech-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .quick-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .action-btn {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1.25rem;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            text-decoration: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .action-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .action-btn.primary {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border: none;
        }
        
        .action-btn.primary:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
        }
        
        .action-btn.success {
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
            color: white;
            border: none;
        }
        
        .action-btn.success:hover {
            background: linear-gradient(135deg, #059669 0%, #065f46 100%);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .stat-card.blue::before { background: linear-gradient(90deg, #3b82f6 0%, #1d4ed8 100%); }
        .stat-card.green::before { background: linear-gradient(90deg, #10b981 0%, #047857 100%); }
        .stat-card.orange::before { background: linear-gradient(90deg, #f59e0b 0%, #d97706 100%); }
        .stat-card.red::before { background: linear-gradient(90deg, #ef4444 0%, #dc2626 100%); }
        .stat-card.purple::before { background: linear-gradient(90deg, #8b5cf6 0%, #7c3aed 100%); }
        .stat-card.teal::before { background: linear-gradient(90deg, #06b6d4 0%, #0891b2 100%); }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }
        
        .stat-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 0.5rem;
            line-height: 1;
        }
        
        .stat-subtext {
            font-size: 0.875rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .workflow-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .workflow-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        
        .workflow-header {
            padding: 1.25rem 1.5rem;
            background: linear-gradient(90deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .workflow-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .order-badge {
            background: #3b82f6;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .orders-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .order-item {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.2s;
        }
        
        .order-item:hover {
            background: #f8fafc;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-main {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }
        
        .patient-name {
            font-weight: 600;
            color: #1e293b;
        }
        
        .patient-id {
            font-size: 0.75rem;
            color: #64748b;
            margin-left: 0.5rem;
        }
        
        .priority {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .priority-stat { background: #fee2e2; color: #dc2626; }
        .priority-urgent { background: #fef3c7; color: #d97706; }
        .priority-routine { background: #dbeafe; color: #1e40af; }
        
        .order-details {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.875rem;
            color: #64748b;
        }
        
        .order-time {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .order-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }
        
        .small-btn {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        
        .small-btn.primary {
            background: #3b82f6;
            color: white;
        }
        
        .small-btn.primary:hover {
            background: #2563eb;
        }
        
        .small-btn.success {
            background: #10b981;
            color: white;
        }
        
        .small-btn.success:hover {
            background: #059669;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .info-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
        }
        
        .info-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }
        
        .activity-icon.test { background: #dbeafe; color: #1d4ed8; }
        .activity-icon.collect { background: #dcfce7; color: #166534; }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-patient {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.875rem;
        }
        
        .activity-action {
            font-size: 0.75rem;
            color: #64748b;
        }
        
        .activity-time {
            font-size: 0.75rem;
            color: #94a3b8;
            text-align: right;
        }
        
        .equipment-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .equipment-status {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-operational { background: #dcfce7; color: #166534; }
        .status-maintenance { background: #fef3c7; color: #d97706; }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #94a3b8;
        }
        
        .no-data-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
            opacity: 0.5;
        }
        
        .refresh-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            cursor: pointer;
            font-size: 1.25rem;
            transition: all 0.3s;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .refresh-btn:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .workflow-section {
                grid-template-columns: 1fr;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .quick-actions {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-user-md" style="color: #3b82f6;"></i>
                    Lab Technician Dashboard
                </h1>
                <!-- <p class="page-subtitle">
                    Welcome back! Focus on your workflow and prioritize urgent tests.
                </p> -->
            </div>
            <div class="tech-info">
                <div class="tech-avatar">
                    <i class="fas fa-flask"></i>
                </div>
                <div>
                    <div style="font-weight: 600; color: #1e293b;">Lab Technician</div>
                    <div style="font-size: 0.875rem; color: #64748b;"><?php echo date('F j, Y g:i A'); ?></div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions" style="margin-bottom: 2rem;">
            <a href="lab_orders.php?status=ordered" class="action-btn primary">
                <i class="fas fa-syringe"></i> Collect Samples
            </a>
            <a href="lab_orders.php?status=in_progress" class="action-btn success">
                <i class="fas fa-vial"></i> Perform Tests
            </a>
            <a href="lab_results_entry.php" class="action-btn">
                <i class="fas fa-edit"></i> Enter Results
            </a>
            <a href="lab_reports.php" class="action-btn">
                <i class="fas fa-file-medical"></i> View Reports
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <!-- Awaiting Collection -->
            <div class="stat-card blue" onclick="window.location.href='lab_orders.php?status=ordered'">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Awaiting Collection</div>
                        <div class="stat-value"><?php echo $stats['awaiting_collection']; ?></div>
                        <div class="stat-subtext">
                            <i class="fas fa-clock"></i>
                            Samples need to be collected
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-syringe" style="color: #3b82f6;"></i>
                    </div>
                </div>
            </div>

            <!-- Awaiting Testing -->
            <div class="stat-card green" onclick="window.location.href='lab_orders.php?status=in_progress'">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Awaiting Testing</div>
                        <div class="stat-value"><?php echo $stats['awaiting_testing']; ?></div>
                        <div class="stat-subtext">
                            <i class="fas fa-vial"></i>
                            Ready for processing
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-vial" style="color: #10b981;"></i>
                    </div>
                </div>
            </div>

            <!-- Urgent Tests -->
            <div class="stat-card red" onclick="window.location.href='lab_orders.php?priority=urgent,stat'">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Urgent Tests</div>
                        <div class="stat-value"><?php echo $stats['urgent_tests']; ?></div>
                        <div class="stat-subtext">
                            <i class="fas fa-exclamation-triangle"></i>
                            Need immediate attention
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
                    </div>
                </div>
            </div>

            <!-- Today's Completed -->
            <div class="stat-card teal" onclick="window.location.href='lab_orders.php?status=completed&date=today'">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Completed Today</div>
                        <div class="stat-value"><?php echo $stats['today_completed']; ?></div>
                        <div class="stat-subtext">
                            <i class="fas fa-check-circle"></i>
                            Tests processed today
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle" style="color: #06b6d4;"></i>
                    </div>
                </div>
            </div>

            <!-- Pending Verification -->
            <!-- <div class="stat-card orange" onclick="window.location.href='lab_orders.php?status=completed&verified=0'">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Pending Verification</div>
                        <div class="stat-value"><?php echo $stats['pending_verification']; ?></div>
                        <div class="stat-subtext">
                            <i class="fas fa-clipboard-check"></i>
                            Awaiting supervisor review
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-check" style="color: #f59e0b;"></i>
                    </div>
                </div>
            </div> -->

            <!-- Overdue Tests -->
            <div class="stat-card purple" onclick="window.location.href='lab_orders.php?overdue=1'">
                <div class="stat-header">
                    <div>
                        <div class="stat-title">Overdue Tests</div>
                        <div class="stat-value"><?php echo $stats['overdue_tests']; ?></div>
                        <div class="stat-subtext">
                            <i class="fas fa-hourglass-end"></i>
                            > 2 hours pending
                        </div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-hourglass-end" style="color: #8b5cf6;"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Workflow Section -->
        <div class="workflow-section">
            <!-- Pending Collection -->
            <div class="workflow-card">
                <div class="workflow-header">
                    <h3 class="workflow-title">
                        <i class="fas fa-syringe" style="color: #3b82f6;"></i>
                        Samples to Collect
                        <?php if (!empty($pending_collection)): ?>
                            <span class="order-badge"><?php echo count($pending_collection); ?></span>
                        <?php endif; ?>
                    </h3>
                    <a href="lab_orders.php?status=ordered" style="font-size: 0.875rem; color: #3b82f6; text-decoration: none; font-weight: 600;">
                        View All
                    </a>
                </div>
                <div class="orders-list">
                    <?php if (!empty($pending_collection)): ?>
                        <?php foreach ($pending_collection as $order): ?>
                            <div class="order-item">
                                <div class="order-main">
                                    <div>
                                        <span class="patient-name"><?php echo htmlspecialchars($order['patient_name']); ?></span>
                                        <span class="patient-id">#<?php echo htmlspecialchars($order['patient_id']); ?></span>
                                    </div>
                                    <span class="priority priority-<?php echo $order['priority']; ?>">
                                        <?php echo strtoupper($order['priority']); ?>
                                    </span>
                                </div>
                                <?php if (!empty($order['clinical_notes'])): ?>
                                    <div style="font-size: 0.875rem; color: #64748b; margin-bottom: 0.5rem;">
                                        <i class="fas fa-sticky-note"></i>
                                        <?php echo htmlspecialchars(substr($order['clinical_notes'], 0, 50)) . (strlen($order['clinical_notes']) > 50 ? '...' : ''); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="order-details">
                                    <div class="order-time">
                                        <i class="fas fa-clock"></i>
                                        Ordered <?php echo $order['order_time']; ?>
                                    </div>
                                    <?php if ($order['minutes_pending'] > 120): ?>
                                        <div style="color: #ef4444; font-weight: 600;">
                                            <i class="fas fa-exclamation-circle"></i>
                                            <?php echo floor($order['minutes_pending'] / 60); ?>h overdue
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="order-actions">
                                    <a href="lab_sample_collection.php?order_id=<?php echo $order['id']; ?>" class="small-btn primary">
                                        <i class="fas fa-syringe"></i> Collect Now
                                    </a>
                                    <a href="lab_order_details.php?order_id=<?php echo $order['id']; ?>" class="small-btn" style="background: #f1f5f9; color: #64748b;">
                                        <i class="fas fa-info-circle"></i> Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <div class="no-data-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <p>No samples awaiting collection</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Testing -->
            <div class="workflow-card">
                <div class="workflow-header">
                    <h3 class="workflow-title">
                        <i class="fas fa-vial" style="color: #10b981;"></i>
                        Samples to Input Results
                        <?php if (!empty($pending_testing)): ?>
                            <span class="order-badge"><?php echo count($pending_testing); ?></span>
                        <?php endif; ?>
                    </h3>
                    <a href="lab_orders.php?status=in_progress" style="font-size: 0.875rem; color: #3b82f6; text-decoration: none; font-weight: 600;">
                        View All
                    </a>
                </div>
                <div class="orders-list">
                    <?php if (!empty($pending_testing)): ?>
                        <?php foreach ($pending_testing as $order): ?>
                            <div class="order-item">
                                <div class="order-main">
                                    <div>
                                        <span class="patient-name"><?php echo htmlspecialchars($order['patient_name']); ?></span>
                                        <span class="patient-id">#<?php echo htmlspecialchars($order['patient_id']); ?></span>
                                    </div>
                                    <span class="priority priority-<?php echo $order['priority']; ?>">
                                        <?php echo strtoupper($order['priority']); ?>
                                    </span>
                                </div>
                                <?php if (!empty($order['clinical_notes'])): ?>
                                    <div style="font-size: 0.875rem; color: #64748b; margin-bottom: 0.5rem;">
                                        <i class="fas fa-sticky-note"></i>
                                        <?php echo htmlspecialchars(substr($order['clinical_notes'], 0, 50)) . (strlen($order['clinical_notes']) > 50 ? '...' : ''); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="order-details">
                                    <div class="order-time">
                                        <i class="fas fa-user-nurse"></i>
                                        Collected by <?php echo htmlspecialchars($order['collected_by_name']); ?>
                                    </div>
                                    <div class="order-time">
                                        <i class="fas fa-clock"></i>
                                        <?php echo $order['collected_time']; ?>
                                    </div>
                                </div>
                                <div class="order-actions">
                                    <a href="lab_results_entry.php?order_id=<?php echo $order['id']; ?>" class="small-btn success">
                                        <i class="fas fa-vial"></i> Enter Results
                                    </a>
                                    <a href="lab_order_details.php?order_id=<?php echo $order['id']; ?>" class="small-btn" style="background: #f1f5f9; color: #64748b;">
                                        <i class="fas fa-info-circle"></i> Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <div class="no-data-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <p>No samples awaiting testing</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Information Grid -->
        <div class="info-grid">
            <!-- Recent Activity -->
            <div class="info-card">
                <h3 class="info-title">
                    <i class="fas fa-history" style="color: #8b5cf6;"></i>
                    Your Recent Activity
                </h3>
                <?php if (!empty($recent_activity)): ?>
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo $activity['action']; ?>">
                                <?php if ($activity['action'] === 'tested'): ?>
                                    <i class="fas fa-vial"></i>
                                <?php else: ?>
                                    <i class="fas fa-syringe"></i>
                                <?php endif; ?>
                            </div>
                            <div class="activity-details">
                                <div class="activity-patient"><?php echo htmlspecialchars($activity['patient_name']); ?></div>
                                <div class="activity-action">
                                    <?php echo ucfirst($activity['action']); ?> • 
                                    <span class="priority priority-<?php echo $activity['priority']; ?>" style="padding: 0.125rem 0.5rem;">
                                        <?php echo strtoupper($activity['priority']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="activity-time">
                                <?php echo date('H:i', strtotime($activity['action_time'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data" style="padding: 2rem;">
                        <div class="no-data-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <p>No recent activity</p>
                    </div>
                <?php endif; ?>
            </div>
         

            <!-- Unpaid Orders -->
            <div class="info-card">
                <h3 class="info-title">
                    <i class="fas fa-money-bill-wave" style="color: #10b981;"></i>
                    Unpaid Lab Orders
                </h3>
                <?php if (!empty($unpaid_orders)): ?>
                    <?php foreach ($unpaid_orders as $order): ?>
                        <div class="activity-item">
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($order['patient_name']); ?></div>
                                <div style="font-size: 0.75rem; color: #64748b;">
                                    Order #<?php echo $order['order_number']; ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: 600; color: #ef4444;">
                                    Ksh <?php echo number_format($order['balance'], 2); ?>
                                </div>
                                <div style="font-size: 0.75rem; color: #64748b;">
                                    <?php echo $order['payment_status']; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data" style="padding: 2rem;">
                        <div class="no-data-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <p>No unpaid orders</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Refresh Button -->
    <button class="refresh-btn" onclick="location.reload();" title="Refresh Dashboard">
        <i class="fas fa-sync-alt"></i>
    </button>

    <script>
        // Auto-refresh every 3 minutes for real-time updates
        setTimeout(function() {
            location.reload();
        }, 180000);

        // Highlight urgent items
        document.addEventListener('DOMContentLoaded', function() {
            const urgentItems = document.querySelectorAll('.priority-stat, .priority-urgent');
            urgentItems.forEach(item => {
                item.parentElement.parentElement.style.borderLeft = '4px solid #ef4444';
                item.parentElement.parentElement.style.backgroundColor = '#fef2f2';
            });
        });
    </script>
</body>
</html>