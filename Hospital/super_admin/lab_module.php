<?php
session_start();
include "includes/db_connect.php";


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user role and ID
$user_role = $_SESSION['role'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

// Get success/error messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Statistics based on user role
if ($user_role === 'Lab Technician') {
    // Lab technician sees orders they need to work on
    $total_orders = $pdo->query("SELECT COUNT(*) FROM lab_orders WHERE status IN ('ordered', 'in_progress')")->fetchColumn();
    $pending_collection = $pdo->query("SELECT COUNT(*) FROM lab_orders WHERE status = 'ordered'")->fetchColumn();
    $pending_testing = $pdo->query("SELECT COUNT(*) FROM lab_orders WHERE status = 'in_progress'")->fetchColumn();
    $completed_today = $pdo->query("SELECT COUNT(*) FROM lab_orders WHERE status = 'completed' AND DATE(completed_at) = CURDATE()")->fetchColumn();
} elseif ($user_role === 'Doctor') {
    // Doctors see their own orders
    $total_orders = $pdo->prepare("SELECT COUNT(*) FROM lab_orders WHERE ordered_by = ?");
    $total_orders->execute([$user_id]);
    $total_orders = $total_orders->fetchColumn();
    
    $pending_orders = $pdo->prepare("SELECT COUNT(*) FROM lab_orders WHERE ordered_by = ? AND status IN ('ordered', 'in_progress')");
    $pending_orders->execute([$user_id]);
    $pending_orders = $pending_orders->fetchColumn();
    
    $completed_today = $pdo->prepare("SELECT COUNT(*) FROM lab_orders WHERE ordered_by = ? AND status = 'completed' AND DATE(completed_at) = CURDATE()");
    $completed_today->execute([$user_id]);
    $completed_today = $completed_today->fetchColumn();
} else {
    // Admin sees everything
    $total_orders = $pdo->query("SELECT COUNT(*) FROM lab_orders")->fetchColumn();
    $pending_orders = $pdo->query("SELECT COUNT(*) FROM lab_orders WHERE status IN ('ordered', 'in_progress')")->fetchColumn();
    $completed_today = $pdo->query("SELECT COUNT(*) FROM lab_orders WHERE status = 'completed' AND DATE(completed_at) = CURDATE()")->fetchColumn();
}

// Get lab orders with filters
$status_filter = $_GET['status'] ?? 'all';
$priority_filter = $_GET['priority'] ?? 'all';

$query = "
    SELECT 
        lo.*,
        p.patient_id,
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        p.age,
        p.gender,
        CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
        CONCAT(uc.first_name, ' ', uc.last_name) as collected_by_name,
        CONCAT(ut.first_name, ' ', ut.last_name) as tested_by_name,
        GROUP_CONCAT(DISTINCT loi.test_name SEPARATOR ', ') as test_names
    FROM lab_orders lo
    JOIN patients p ON lo.patient_id = p.id
    JOIN users u ON lo.ordered_by = u.id
    LEFT JOIN users uc ON lo.collected_by = uc.id
    LEFT JOIN users ut ON lo.tested_by = ut.id
    LEFT JOIN lab_order_items loi ON lo.id = loi.lab_order_id
    WHERE 1=1
";

$params = [];

// Role-based filtering
if ($user_role === 'Lab Technician') {
    $query .= " AND lo.status IN ('ordered', 'in_progress', 'completed')";
} elseif ($user_role === 'Doctor') {
    $query .= " AND lo.ordered_by = ?";
    $params[] = $user_id;
}

if ($status_filter !== 'all') {
    $query .= " AND lo.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter !== 'all') {
    $query .= " AND lo.priority = ?";
    $params[] = $priority_filter;
}

$query .= " GROUP BY lo.id ORDER BY 
    CASE lo.priority 
        WHEN 'stat' THEN 1
        WHEN 'urgent' THEN 2
        WHEN 'routine' THEN 3
        ELSE 4
    END ASC, 
    lo.order_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$lab_orders = $stmt->fetchAll();
include "includes/sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Module - AuraCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Copy all the enhanced styles from your original lab_module.php */
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card.warning { border-left: 4px solid #f59e0b; }
        .stat-card.success { border-left: 4px solid #10b981; }
        .stat-card.info { border-left: 4px solid #3b82f6; }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* Orders List */
        .orders-list {
            display: grid;
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .order-item {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .order-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: #3b82f6;
        }
        
        .order-item.priority-stat::before { background: #dc2626; }
        .order-item.priority-urgent::before { background: #f59e0b; }
        .order-item.priority-routine::before { background: #10b981; }
        
        .order-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        /* Status Badges */
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-ordered { background: #fef3c7; color: #d97706; border: 1px solid #fcd34d; }
        .status-in_progress { background: #dbeafe; color: #1d4ed8; border: 1px solid #93c5fd; }
        .status-completed { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
        }
        
        .btn-secondary {
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-info {
    background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(6, 182, 212, 0.3);
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
    <div class="main-content" style="margin-left: 300px;">
        <div class="dashboard-header" style="margin-bottom: 2rem;">
            <div>
                <h1 style="font-size: 1.875rem; font-weight: 700; color: #1e293b; margin-bottom: 0.5rem;">
                    <i class="fas fa-flask" style="color: #3b82f6; margin-right: 0.5rem;"></i>
                    Laboratory Module
                    <?php if ($user_role === 'Lab Technician'): ?>
                        - Technician Dashboard
                    <?php elseif ($user_role === 'Doctor'): ?>
                        - My Lab Orders
                    <?php endif; ?>
                </h1>
                <p style="color: #64748b;">
                    <?php if ($user_role === 'Lab Technician'): ?>
                        Manage sample collection and testing workflow
                    <?php elseif ($user_role === 'Doctor'): ?>
                        View your lab orders and results
                    <?php else: ?>
                        Manage lab orders and test results
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <!-- Display Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Role-specific Statistics -->
        <div class="stats-grid">
            <?php if ($user_role === 'Lab Technician'): ?>
                <div class="stat-card warning">
                    <div class="stat-value"><?php echo $pending_collection; ?></div>
                    <div class="stat-label">Awaiting Collection</div>
                </div>
                <div class="stat-card info">
                    <div class="stat-value"><?php echo $pending_testing; ?></div>
                    <div class="stat-label">Awaiting Testing</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_orders; ?></div>
                    <div class="stat-label">Total Active Orders</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-value"><?php echo $completed_today; ?></div>
                    <div class="stat-label">Completed Today</div>
                </div>
            <?php else: ?>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_orders; ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-value"><?php echo $pending_orders ?? 0; ?></div>
                    <div class="stat-label">Pending Results</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-value"><?php echo $completed_today; ?></div>
                    <div class="stat-label">Completed Today</div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Orders List -->
        <div class="orders-list">
            <?php if (empty($lab_orders)): ?>
                <div style="text-align: center; padding: 4rem; color: #64748b; background: white; border-radius: 1rem;">
                    <i class="fas fa-flask" style="font-size: 4rem; margin-bottom: 1.5rem; color: #cbd5e1;"></i>
                    <h3 style="color: #64748b; margin-bottom: 0.5rem;">No Lab Orders Found</h3>
                    <p>No orders match your current filter criteria.</p>
                </div>
            <?php else: ?>
                <?php foreach ($lab_orders as $order): ?>
                    <div class="order-item priority-<?php echo $order['priority']; ?>">
                        <div style="display: flex; justify-content: space-between; align-items: start; gap: 1.5rem;">
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #1e293b; font-size: 1.1rem; margin-bottom: 0.5rem;">
                                    <?php echo htmlspecialchars($order['patient_name']); ?>
                                    <?php if ($user_role === 'Doctor' && $order['status'] === 'completed'): ?>
                                        <span style="color: #10b981; font-size: 0.75rem; margin-left: 0.5rem;">
                                            <i class="fas fa-check-circle"></i> Results Ready
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div style="color: #64748b; font-size: 0.875rem; margin-bottom: 1rem;">
                                    ID: <?php echo htmlspecialchars($order['patient_id']); ?>
                                    • <?php echo $order['age']; ?> years • <?php echo $order['gender']; ?>
                                </div>
                                
                                <div style="display: flex; flex-wrap: wrap; gap: 1rem; font-size: 0.875rem;">
                                    <span style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-user-md" style="color: #3b82f6;"></i>
                                        Dr. <?php echo htmlspecialchars($order['doctor_name']); ?>
                                    </span>
                                    <span style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-clock" style="color: #3b82f6;"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($order['order_date'])); ?>
                                    </span>
                                    <span style="display: flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-vial" style="color: #3b82f6;"></i>
                                        <?php echo htmlspecialchars($order['test_names'] ?? 'Tests ordered'); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div style="display: flex; flex-direction: column; gap: 0.75rem; align-items: end;">
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                </span>
                                
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
    <!-- Lab Technician Actions -->
    <?php if ($user_role === 'Lab Technician' || $user_role === 'Super Admin'): ?>
        <?php if ($order['status'] === 'ordered'): ?>
            <a href="lab_sample_collection.php?order_id=<?php echo $order['id']; ?>" class="btn btn-primary">
                <i class="fas fa-syringe"></i> Collect Sample
            </a>
        <?php elseif ($order['status'] === 'in_progress'): ?>
            <a href="lab_results_entry.php?order_id=<?php echo $order['id']; ?>" class="btn btn-success">
                <i class="fas fa-edit"></i> Enter Results
            </a>
        <?php endif; ?>
        
        <!-- View Results button for Lab Technicians (for completed orders) -->
        <?php if ($order['status'] === 'completed'): ?>
        <a href="lab_reports.php?order_id=<?php echo $order['id']; ?>" class="btn btn-info" target="_blank">
            <i class="fas fa-file-medical"></i> View Results
        </a>
    <?php endif; ?>
    <?php endif; ?>
    
    <!-- Doctor Actions -->
    <?php if ($user_role === 'Doctor'): ?>
        <?php if ($order['status'] === 'completed'): ?>
            <a href="lab_reports.php?order_id=<?php echo $order['id']; ?>" class="btn btn-success" target="_blank">
                <i class="fas fa-file-medical"></i> View Results
            </a>
        <?php else: ?>
            <span class="btn btn-secondary" style="opacity: 0.7;">
                <i class="fas fa-clock"></i> Pending
            </span>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- View Results button for other roles (Admin, etc.) -->
    <?php if (($user_role !== 'Lab Technician' && $user_role !== 'Doctor' && $user_role !== 'Super Admin') && $order['status'] === 'completed'): ?>
        <a href="lab_reports.php?order_id=<?php echo $order['id']; ?>" class="btn btn-info" target="_blank">
            <i class="fas fa-file-medical"></i> View Results
        </a>
    <?php endif; ?>
    
    <a href="lab_order_details.php?order_id=<?php echo $order['id']; ?>" class="btn btn-secondary">
        <i class="fas fa-info-circle"></i> Details
    </a>
</div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>