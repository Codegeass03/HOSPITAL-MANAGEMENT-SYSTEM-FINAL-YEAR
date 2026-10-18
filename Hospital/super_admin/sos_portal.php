<?php
session_start();
require_once "includes/db_connect.php";

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Super Admin', 'Admin', 'Doctor', 'Nurse', 'Emergency Staff'])) {
    header("Location: login.php");
    exit();
}

// Get messages
$success_message = $_SESSION['success'] ?? null;
$error_message = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Pagination settings
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter settings
$status_filter = $_GET['status'] ?? 'active';
$priority_filter = $_GET['priority'] ?? 'all';
$date_filter = $_GET['date'] ?? 'today';

// Fetch SOS alerts with filters
try {
    $query = "
        SELECT 
            sa.*,
            p.patient_id as patient_code,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.age,
            p.gender,
            p.blood_type,
            p.phone as patient_phone,
            p.emergency_contact_name,
            p.emergency_contact_phone,
            CONCAT(u.first_name, ' ', u.last_name) as assigned_staff_name,
            u.phone as staff_phone,
            et.team_name,
            et.team_type,
            et.contact_number as team_contact,
            et.vehicle_number,
            COUNT(rl.id) as action_count,
            TIMESTAMPDIFF(MINUTE, sa.created_at, NOW()) as minutes_ago,
            CASE 
                WHEN TIMESTAMPDIFF(MINUTE, sa.created_at, NOW()) < 5 THEN 'just-now'
                WHEN TIMESTAMPDIFF(MINUTE, sa.created_at, NOW()) < 30 THEN 'recent'
                ELSE 'older'
            END as time_category
        FROM sos_alerts sa
        LEFT JOIN patients p ON sa.patient_id = p.id
        LEFT JOIN users u ON sa.assigned_to = u.id
        LEFT JOIN emergency_teams et ON sa.response_team_id = et.id
        LEFT JOIN emergency_response_log rl ON sa.id = rl.alert_id
        WHERE 1=1
    ";
    
    $params = [];
    $types = '';
    
    // Apply status filter
    if ($status_filter !== 'all') {
        $query .= " AND sa.status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
    
    // Apply priority filter
    if ($priority_filter !== 'all') {
        $query .= " AND sa.priority = ?";
        $params[] = $priority_filter;
        $types .= 's';
    }
    
    // Apply date filter
    if ($date_filter === 'today') {
        $query .= " AND DATE(sa.created_at) = CURDATE()";
    } elseif ($date_filter === 'yesterday') {
        $query .= " AND DATE(sa.created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    } elseif ($date_filter === 'week') {
        $query .= " AND sa.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    }
    
    $query .= " GROUP BY sa.id ORDER BY 
        CASE sa.priority
            WHEN 'critical' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
        END,
        sa.created_at DESC
        LIMIT ? OFFSET ?";
    
    // Add LIMIT and OFFSET as integers
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    // Prepare and execute with parameter binding
    $stmt = $pdo->prepare($query);
    
    // Bind parameters dynamically
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            // Determine parameter type
            $param_type = PDO::PARAM_STR;
            if (is_int($value)) {
                $param_type = PDO::PARAM_INT;
            }
            $stmt->bindValue($key + 1, $value, $param_type);
        }
    }
    
    $stmt->execute();
    $sos_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count total alerts for pagination
    $count_query = "SELECT COUNT(*) as total FROM sos_alerts sa WHERE 1=1";
    $count_params = [];
    
    if ($status_filter !== 'all') {
        $count_query .= " AND sa.status = ?";
        $count_params[] = $status_filter;
    }
    
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_alerts = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_alerts / $limit);
    
} catch (PDOException $e) {
    $sos_alerts = [];
    $error_message = "Failed to load SOS alerts: " . $e->getMessage();
}

// Fetch available emergency teams
try {
    $teams_stmt = $pdo->query("
        SELECT * FROM emergency_teams 
        WHERE current_status = 'available'
        ORDER BY team_type, team_name
    ");
    $available_teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $available_teams = [];
}

// Fetch active staff
try {
    $staff_stmt = $pdo->query("
        SELECT id, CONCAT(first_name, ' ', last_name) as name, role, department, phone
        FROM users 
        WHERE status = 'active' 
        AND role IN ('Doctor', 'Nurse', 'Paramedic', 'Emergency Staff')
        ORDER BY role, first_name
    ");
    $available_staff = $staff_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $available_staff = [];
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $alert_id = $_POST['alert_id'] ?? 0;
    
    try {
        switch ($action) {
            case 'acknowledge':
                $stmt = $pdo->prepare("
                    UPDATE sos_alerts 
                    SET status = 'acknowledged', 
                        assigned_to = ?,
                        responded_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $alert_id]);
                
                // Log the action
                $log_stmt = $pdo->prepare("
                    INSERT INTO emergency_response_log 
                    (alert_id, action, performed_by, details)
                    VALUES (?, 'assigned', ?, 'Alert acknowledged by staff')
                ");
                $log_stmt->execute([$alert_id, $_SESSION['user_id']]);
                
                $_SESSION['success'] = "Alert acknowledged successfully";
                break;
                
            case 'assign_team':
                $team_id = $_POST['team_id'] ?? null;
                $estimated_arrival = $_POST['estimated_arrival'] ?? null;
                
                $stmt = $pdo->prepare("
                    UPDATE sos_alerts 
                    SET status = 'responded',
                        response_team_id = ?,
                        estimated_arrival = ?,
                        assigned_to = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$team_id, $estimated_arrival, $_SESSION['user_id'], $alert_id]);
                
                // Update team status
                $team_stmt = $pdo->prepare("
                    UPDATE emergency_teams 
                    SET current_status = 'busy',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $team_stmt->execute([$team_id]);
                
                // Log the action
                $log_stmt = $pdo->prepare("
                    INSERT INTO emergency_response_log 
                    (alert_id, action, performed_by, details)
                    VALUES (?, 'dispatched', ?, 'Response team dispatched')
                ");
                $log_stmt->execute([$alert_id, $_SESSION['user_id']]);
                
                $_SESSION['success'] = "Response team assigned successfully";
                break;
                
            case 'update_status':
                $new_status = $_POST['new_status'] ?? '';
                $notes = $_POST['notes'] ?? '';
                
                $stmt = $pdo->prepare("
                    UPDATE sos_alerts 
                    SET status = ?,
                        notes = CONCAT(IFNULL(notes, ''), '\n', ?),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                
                if ($new_status === 'resolved') {
                    $stmt = $pdo->prepare("
                        UPDATE sos_alerts 
                        SET status = ?,
                            resolved_at = NOW(),
                            notes = CONCAT(IFNULL(notes, ''), '\n', ?),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                }
                
                $stmt->execute([$new_status, $notes, $alert_id]);
                
                // Log the action
                $action_map = [
                    'active' => 'treatment_started',
                    'resolved' => 'completed',
                    'cancelled' => 'cancelled'
                ];
                
                if (isset($action_map[$new_status])) {
                    $log_stmt = $pdo->prepare("
                        INSERT INTO emergency_response_log 
                        (alert_id, action, performed_by, details)
                        VALUES (?, ?, ?, ?)
                    ");
                    $log_stmt->execute([$alert_id, $action_map[$new_status], $_SESSION['user_id'], $notes]);
                }
                
                $_SESSION['success'] = "Alert status updated successfully";
                break;
                
            case 'add_note':
                $note = $_POST['note'] ?? '';
                
                $stmt = $pdo->prepare("
                    UPDATE sos_alerts 
                    SET notes = CONCAT(IFNULL(notes, ''), '\n[', NOW(), '] ', ?),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$note, $alert_id]);
                
                $_SESSION['success'] = "Note added successfully";
                break;
        }
        
        header("Location: sos_portal.php");
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error'] = "Action failed: " . $e->getMessage();
        header("Location: sos_portal.php");
        exit();
    }
}

// Get statistics
try {
    $stats_stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_alerts,
            SUM(CASE WHEN status IN ('pending', 'active') THEN 1 ELSE 0 END) as active_alerts,
            SUM(CASE WHEN priority = 'critical' AND status IN ('pending', 'active') THEN 1 ELSE 0 END) as critical_alerts,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_alerts,
            SUM(CASE WHEN status = 'resolved' AND DATE(resolved_at) = CURDATE() THEN 1 ELSE 0 END) as resolved_today
        FROM sos_alerts
    ");
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = [
        'total_alerts' => 0,
        'active_alerts' => 0,
        'critical_alerts' => 0,
        'today_alerts' => 0,
        'resolved_today' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOS Emergency Portal | AuraCare HMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        :root {
            --primary-blue: #7d1baa;
            --light-blue: #97017e;
            --medium-blue: #90caf9;
            --dark-blue: #1565c0;
            --alert-red: #e53935;
            --warning-orange: #ff9800;
            --success-green: #4caf50;
            --neutral-gray: #f5f5f5;
            --text-dark: #263238;
            --text-light: #546e7a;
            --white: #ffffff;
            --border: #e0e0e0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #e6f5ff 0%, #af8dff 100%);
            color: var(--text-dark);
            min-height: 100vh;
            line-height: 1.6;
        }
        
        .sos-portal-container {
            padding: 25px;
            margin-left: 380px;
            max-width: 1400px;
            width: 100%;
            
        }
        
        @media (max-width: 1200px) {
            .sos-portal-container {
                margin-left: 0;
                padding: 15px;
            }
        }
        
        /* Header */
        .portal-header {
            background: var(--white);
            padding: 30px;
            border-radius: 16px;
            margin-bottom: 25px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 20px rgba(30, 136, 229, 0.08);
            position: relative;
            overflow: hidden;
        }
        
        .portal-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-blue), var(--medium-blue), var(--success-green));
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .portal-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .portal-title h1 {
            font-size: 28px;
            color: var(--dark-blue);
            font-weight: 700;
            background: linear-gradient(135deg, var(--dark-blue), var(--primary-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .portal-title .badge {
            background: var(--alert-red);
            color: white;
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            animation: pulse 2s infinite;
            box-shadow: 0 4px 12px rgba(229, 57, 53, 0.3);
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .live-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--success-green);
            font-weight: 500;
            background: rgba(76, 175, 80, 0.1);
            padding: 8px 16px;
            border-radius: 20px;
        }
        
        .live-dot {
            width: 8px;
            height: 8px;
            background: var(--success-green);
            border-radius: 50%;
            animation: blink 1s infinite;
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: var(--white);
            padding: 25px;
            border-radius: 12px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(30, 136, 229, 0.15);
            border-color: var(--primary-blue);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-blue);
        }
        
        .stat-card.critical::before { background: var(--alert-red); }
        .stat-card.active::before { background: var(--warning-orange); }
        .stat-card.total::before { background: var(--primary-blue); }
        .stat-card.resolved::before { background: var(--success-green); }
        
        .stat-value {
            font-size: 36px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 5px;
            line-height: 1;
        }
        
        .stat-card.critical .stat-value { color: var(--alert-red); }
        .stat-card.active .stat-value { color: var(--warning-orange); }
        .stat-card.total .stat-value { color: var(--primary-blue); }
        .stat-card.resolved .stat-value { color: var(--success-green); }
        
        .stat-label {
            color: var(--text-light);
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Filters */
        .filters-bar {
            background: var(--white);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            border: 1px solid var(--border);
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-label {
            color: var(--text-light);
            font-size: 14px;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .filter-select {
            background: var(--white);
            color: var(--text-dark);
            border: 1px solid var(--border);
            padding: 10px 16px;
            border-radius: 8px;
            font-size: 14px;
            min-width: 160px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(30, 136, 229, 0.1);
        }
        
        .refresh-btn {
            background: var(--primary-blue);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 14px;
        }
        
        .refresh-btn:hover {
            background: var(--dark-blue);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 136, 229, 0.3);
        }
        
        /* Map Container */
        .map-container {
            height: 400px;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 25px;
            border: 1px solid var(--border);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        #emergencyMap {
            height: 100%;
            width: 100%;
        }
        
        /* Alerts Grid */
        .alerts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .alerts-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .alert-card {
            background: var(--white);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        .alert-card:hover {
            border-color: var(--primary-blue);
            box-shadow: 0 8px 25px rgba(30, 136, 229, 0.15);
            transform: translateY(-3px);
        }
        
        .alert-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        }
        
        .alert-priority {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .priority-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .priority-critical { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .priority-high { background: #fff3e0; color: #ef6c00; border: 1px solid #ffe0b2; }
        .priority-medium { background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; }
        .priority-low { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        
        .alert-status {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending { background: #fff3e0; color: #f57c00; border: 1px solid #ffe0b2; }
        .status-active { background: #fce4ec; color: #c2185b; border: 1px solid #f8bbd9; }
        .status-acknowledged { background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; }
        .status-responded { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .status-resolved { background: #e0f2f1; color: #00695c; border: 1px solid #b2dfdb; }
        .status-cancelled { background: #f5f5f5; color: #616161; border: 1px solid #e0e0e0; }
        
        .alert-body {
            padding: 20px;
        }
        
        .patient-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .patient-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .patient-details h3 {
            font-size: 18px;
            color: var(--text-dark);
            margin-bottom: 4px;
            font-weight: 600;
        }
        
        .patient-meta {
            color: var(--text-light);
            font-size: 13px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        
        .alert-details {
            margin-bottom: 15px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .detail-label {
            color: var(--text-light);
            font-weight: 500;
        }
        
        .detail-value {
            color: var(--text-dark);
            font-weight: 600;
            text-align: right;
            max-width: 200px;
            word-break: break-word;
        }
        
        .medical-summary {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 13px;
            line-height: 1.6;
            color: var(--text-dark);
            max-height: 120px;
            overflow-y: auto;
            border: 1px solid var(--border);
        }
        
        .alert-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        
        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        
        .btn-acknowledge {
            background: var(--primary-blue);
            color: white;
        }
        
        .btn-acknowledge:hover {
            background: var(--dark-blue);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 136, 229, 0.3);
        }
        
        .btn-assign {
            background: var(--success-green);
            color: white;
        }
        
        .btn-assign:hover {
            background: #388e3c;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        .btn-resolve {
            background: #7e57c2;
            color: white;
        }
        
        .btn-resolve:hover {
            background: #673ab7;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(126, 87, 194, 0.3);
        }
        
        .btn-cancel {
            background: #78909c;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #607d8b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(120, 144, 156, 0.3);
        }
        
        .btn-view {
            background: transparent;
            color: var(--primary-blue);
            border: 1px solid var(--primary-blue);
        }
        
        .btn-view:hover {
            background: rgba(30, 136, 229, 0.1);
            transform: translateY(-2px);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
            background: var(--white);
            border-radius: 12px;
            border: 2px dashed var(--border);
            margin: 20px 0;
        }
        
        .empty-icon {
            font-size: 64px;
            color: var(--medium-blue);
            margin-bottom: 20px;
            opacity: 0.7;
        }
        
        .empty-title {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--text-dark);
            font-weight: 600;
        }
        
        .empty-subtitle {
            color: var(--text-light);
            max-width: 400px;
            margin: 0 auto;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 40px;
        }
        
        .page-link {
            padding: 10px 16px;
            background: var(--white);
            color: var(--text-dark);
            border: 1px solid var(--border);
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s;
            font-weight: 500;
            min-width: 45px;
            text-align: center;
        }
        
        .page-link:hover {
            background: var(--light-blue);
            border-color: var(--primary-blue);
            color: var(--primary-blue);
        }
        
        .page-link.active {
            background: var(--primary-blue);
            border-color: var(--primary-blue);
            color: white;
            box-shadow: 0 4px 12px rgba(30, 136, 229, 0.3);
        }
        
        /* Teams Section */
        .teams-section {
            background: var(--white);
            padding: 25px;
            border-radius: 12px;
            margin-top: 30px;
            border: 1px solid var(--border);
        }
        
        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .team-card {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid var(--border);
            transition: all 0.3s;
        }
        
        .team-card:hover {
            background: var(--white);
            border-color: var(--primary-blue);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }
        
        .team-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .team-name {
            font-weight: 700;
            color: var(--text-dark);
            font-size: 16px;
        }
        
        .team-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-available { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .status-busy { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        
        /* Section Headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-header h2 {
            font-size: 22px;
            color: var(--dark-blue);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header h2 i {
            color: var(--primary-blue);
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .portal-header {
                padding: 20px;
            }
            
            .header-top {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-select {
                width: 100%;
            }
            
            .alerts-grid {
                grid-template-columns: 1fr;
            }
            
            .teams-grid {
                grid-template-columns: 1fr;
            }
            
            .alert-actions {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Animation for new alerts */
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        /* Badge for new alerts */
        .new-alert-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--alert-red);
            color: white;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            animation: pulse 1.5s infinite;
        }
        
        /* Time indicator */
        .time-indicator {
            font-size: 12px;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .time-indicator i {
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="sos-portal-container">
        <!-- Alert Messages -->
        <?php if ($success_message): ?>
            <div style="background: #e8f5e9; color: #2e7d32; padding: 16px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; border: 1px solid #c8e6c9;">
                <i class="fas fa-check-circle" style="font-size: 20px;"></i>
                <div style="font-weight: 500;"><?php echo htmlspecialchars($success_message); ?></div>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div style="background: #ffebee; color: #c62828; padding: 16px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; border: 1px solid #ffcdd2;">
                <i class="fas fa-exclamation-circle" style="font-size: 20px;"></i>
                <div style="font-weight: 500;"><?php echo htmlspecialchars($error_message); ?></div>
            </div>
        <?php endif; ?>
        
        <!-- Portal Header -->
        <div class="portal-header">
            <div class="header-top">
                <div class="portal-title">
                    <i class="fas fa-first-aid" style="font-size: 32px; color: var(--alert-red);"></i>
                    <h1>SOS Emergency Portal</h1>
                    <span class="badge">LIVE</span>
                </div>
                <div class="live-indicator">
                    <span class="live-dot"></span>
                    <span>Real-time Monitoring</span>
                    <span id="lastUpdate"><?php echo date('H:i:s'); ?></span>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card critical">
                    <div class="stat-value"><?php echo $stats['critical_alerts']; ?></div>
                    <div class="stat-label">Critical Alerts</div>
                </div>
                <div class="stat-card active">
                    <div class="stat-value"><?php echo $stats['active_alerts']; ?></div>
                    <div class="stat-label">Active Emergencies</div>
                </div>
                <div class="stat-card total">
                    <div class="stat-value"><?php echo $stats['today_alerts']; ?></div>
                    <div class="stat-label">Today's Alerts</div>
                </div>
                <!-- <div class="stat-card resolved">
                    <div class="stat-value"><?php echo $stats['resolved_today']; ?></div>
                    <div class="stat-label">Resolved Today</div>
                </div> -->
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-bar">
            <div class="filter-group">
                <span class="filter-label">Status:</span>
                <select class="filter-select" id="statusFilter" onchange="updateFilters()">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="acknowledged" <?php echo $status_filter === 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
                    <option value="responded" <?php echo $status_filter === 'responded' ? 'selected' : ''; ?>>Responded</option>
                </select>
            </div>
            
            <div class="filter-group">
                <span class="filter-label">Priority:</span>
                <select class="filter-select" id="priorityFilter" onchange="updateFilters()">
                    <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All Priorities</option>
                    <option value="critical" <?php echo $priority_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                    <option value="high" <?php echo $priority_filter === 'high' ? 'selected' : ''; ?>>High</option>
                    <option value="medium" <?php echo $priority_filter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="low" <?php echo $priority_filter === 'low' ? 'selected' : ''; ?>>Low</option>
                </select>
            </div>
            
            <div class="filter-group">
                <span class="filter-label">Time:</span>
                <select class="filter-select" id="dateFilter" onchange="updateFilters()">
                    <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                    <option value="yesterday" <?php echo $date_filter === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                    <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                </select>
            </div>
            
            <button class="refresh-btn" onclick="location.reload()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
        
        <!-- Emergency Map -->
        <div class="map-container">
            <div id="emergencyMap"></div>
        </div>
        
        <!-- SOS Alerts -->
        <div class="section-header">
            <h2><i class="fas fa-exclamation-triangle"></i> Active Emergency Alerts</h2>
            <div class="time-indicator">
                <i class="far fa-clock"></i>
                <span>Last updated: <?php echo date('H:i:s'); ?></span>
            </div>
        </div>
        
        <?php if (empty($sos_alerts)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle empty-icon"></i>
                <h3 class="empty-title">No Active Emergencies</h3>
                <p class="empty-subtitle">All systems are currently stable and no emergency alerts are pending.</p>
            </div>
        <?php else: ?>
            <div class="alerts-grid">
                <?php foreach ($sos_alerts as $alert): ?>
                    <div class="alert-card" data-alert-id="<?php echo $alert['id']; ?>">
                        <div class="alert-header">
                            <div class="alert-priority">
                                <span class="priority-badge priority-<?php echo $alert['priority']; ?>">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?php echo strtoupper($alert['priority']); ?> PRIORITY
                                </span>
                                <span class="alert-status status-<?php echo $alert['status']; ?>">
                                    <?php echo strtoupper($alert['status']); ?>
                                </span>
                            </div>
                            <div class="time-indicator">
                                <i class="far fa-clock"></i> 
                                <?php echo $alert['minutes_ago']; ?> min ago
                            </div>
                        </div>
                        
                        <div class="alert-body">
                            <div class="patient-info">
                                <div class="patient-avatar">
                                    <?php echo strtoupper(substr($alert['patient_name'], 0, 1)); ?>
                                </div>
                                <div class="patient-details">
                                    <h3><?php echo htmlspecialchars($alert['patient_name']); ?></h3>
                                    <div class="patient-meta">
                                        <span>ID: <?php echo htmlspecialchars($alert['patient_code']); ?></span>
                                        <span>•</span>
                                        <span><?php echo $alert['age']; ?>y</span>
                                        <span>•</span>
                                        <span><?php echo $alert['gender']; ?></span>
                                        <span>•</span>
                                        <span>Blood: <?php echo $alert['blood_type']; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert-details">
                                <div class="detail-row">
                                    <span class="detail-label">Emergency Type:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($alert['emergency_type']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Location:</span>
                                    <span class="detail-value">
                                        <?php if ($alert['latitude'] && $alert['longitude']): ?>
                                            <?php echo number_format($alert['latitude'], 4); ?>, <?php echo number_format($alert['longitude'], 4); ?>
                                        <?php else: ?>
                                            <span style="color: #78909c;">Location not available</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if ($alert['assigned_staff_name']): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Assigned To:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($alert['assigned_staff_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($alert['team_name']): ?>
                                    <div class="detail-row">
                                        <span class="detail-label">Response Team:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($alert['team_name']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($alert['medical_summary']): ?>
                                <div class="medical-summary">
                                    <strong>Medical Summary:</strong><br>
                                    <?php echo nl2br(htmlspecialchars(substr($alert['medical_summary'], 0, 200))); ?>
                                    <?php if (strlen($alert['medical_summary']) > 200): ?>...<?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                           <div class="alert-actions">
    <?php if ($alert['status'] === 'pending'): ?>
        <button class="action-btn btn-acknowledge" onclick="acknowledgeAlert(<?php echo $alert['id']; ?>)">
            <i class="fas fa-check"></i> Acknowledge
        </button>
    <?php endif; ?>
    
    <?php if (in_array($alert['status'], ['pending', 'active', 'acknowledged'])): ?>
        <button class="action-btn btn-assign" onclick="showAssignModal(<?php echo $alert['id']; ?>)">
            <i class="fas fa-ambulance"></i> Assign Team
        </button>
    <?php endif; ?>
    
    <?php if ($alert['status'] === 'responded'): ?>
        <button class="action-btn btn-acknowledge" onclick="acknowledgeAlertComplete(<?php echo $alert['id']; ?>)">
            <i class="fas fa-check-double"></i> Acknowledged
        </button>
    <?php endif; ?>
    
    <?php if ($alert['status'] === 'responded' && in_array($_SESSION['role'], ['Doctor', 'Nurse'])): ?>
        <button class="action-btn btn-resolve" onclick="showResolveModal(<?php echo $alert['id']; ?>)">
            <i class="fas fa-check-double"></i> Mark Resolved
        </button>
    <?php endif; ?>
    
    <button class="action-btn btn-view" onclick="showAlertDetails(<?php echo $alert['id']; ?>)">
        <i class="fas fa-eye"></i> View Details
    </button>
    
    <?php if ($alert['status'] === 'responded' && in_array($_SESSION['role'], ['Doctor', 'Nurse', 'Admin', 'Super Admin'])): ?>
        <button class="action-btn btn-cancel" onclick="showCancelModal(<?php echo $alert['id']; ?>)">
            <i class="fas fa-times"></i> Cancel
        </button>
    <?php endif; ?>
</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&priority=<?php echo $priority_filter; ?>&date=<?php echo $date_filter; ?>"
                           class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Available Teams -->
        <?php if (!empty($available_teams)): ?>
            <div class="teams-section">
                <div class="section-header">
                    <h2><i class="fas fa-ambulance"></i> Available Response Teams</h2>
                    <span style="color: var(--text-light); font-size: 14px;"><?php echo count($available_teams); ?> teams available</span>
                </div>
                <div class="teams-grid">
                    <?php foreach ($available_teams as $team): ?>
                        <div class="team-card">
                            <div class="team-header">
                                <div class="team-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                                <div class="team-status status-available">Available</div>
                            </div>
                            <div style="color: var(--text-light); font-size: 14px; margin-bottom: 8px;">
                                <i class="fas fa-stethoscope"></i> <?php echo ucfirst($team['team_type']); ?> Team
                            </div>
                            <div style="color: var(--text-light); font-size: 13px; margin-bottom: 8px;">
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($team['contact_number']); ?>
                            </div>
                            <div style="color: var(--text-light); font-size: 13px;">
                                <i class="fas fa-car"></i> <?php echo htmlspecialchars($team['vehicle_number']); ?>
                            </div>
                            <?php if ($team['specializations']): ?>
                                <div style="margin-top: 12px; font-size: 12px; color: var(--text-dark); background: rgba(30, 136, 229, 0.1); padding: 6px 10px; border-radius: 4px;">
                                    <strong>Specializations:</strong> <?php echo htmlspecialchars($team['specializations']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Initialize map
        let map = null;
        let markers = [];
        
        function initMap() {
            map = L.map('emergencyMap').setView([-1.2921, 36.8219], 13); // Nairobi coordinates
            
            // Use a light theme map
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                attribution: '© OpenStreetMap contributors, © CARTO'
            }).addTo(map);
            
            // Add markers for alerts with locations
            <?php foreach ($sos_alerts as $alert): ?>
                <?php if ($alert['latitude'] && $alert['longitude']): ?>
                    // Determine marker color based on priority
                    let markerColor = '#1e88e5'; // default blue
                    if ('<?php echo $alert['priority']; ?>' === 'critical') markerColor = '#e53935';
                    if ('<?php echo $alert['priority']; ?>' === 'high') markerColor = '#ff9800';
                    if ('<?php echo $alert['priority']; ?>' === 'medium') markerColor = '#1e88e5';
                    if ('<?php echo $alert['priority']; ?>' === 'low') markerColor = '#4caf50';
                    
                    // Create custom icon
                    let customIcon = L.divIcon({
                        className: 'custom-marker',
                        html: `<div style="background-color: ${markerColor}; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;">!</div>`,
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    });
                    
                    var marker = L.marker([<?php echo $alert['latitude']; ?>, <?php echo $alert['longitude']; ?>], {icon: customIcon})
                        .addTo(map)
                        .bindPopup(`
                            <div style="padding: 10px; min-width: 200px;">
                                <div style="font-weight: bold; color: #1565c0; margin-bottom: 5px;">
                                    <?php echo addslashes($alert['patient_name']); ?>
                                </div>
                                <div style="font-size: 12px; color: #666; margin-bottom: 5px;">
                                    <?php echo addslashes($alert['emergency_type']); ?>
                                </div>
                                <div style="font-size: 11px; color: #999;">
                                    Priority: <?php echo $alert['priority']; ?><br>
                                    Status: <?php echo $alert['status']; ?>
                                </div>
                            </div>
                        `);
                    markers.push(marker);
                <?php endif; ?>
            <?php endforeach; ?>
            
            // Fit bounds to show all markers
            if (markers.length > 0) {
                var group = new L.featureGroup(markers);
                map.fitBounds(group.getBounds().pad(0.1));
            }
        }
        
        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            updateLastUpdateTime();
            
            // Auto-refresh every 30 seconds
            setInterval(function() {
                updateLastUpdateTime();
            }, 30000);
        });
        
        function updateLastUpdateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            document.getElementById('lastUpdate').textContent = timeString;
        }
        
        function updateFilters() {
            const status = document.getElementById('statusFilter').value;
            const priority = document.getElementById('priorityFilter').value;
            const date = document.getElementById('dateFilter').value;
            
            window.location.href = `sos_portal.php?status=${status}&priority=${priority}&date=${date}`;
        }
        
        function acknowledgeAlert(alertId) {
            if (confirm('Are you sure you want to acknowledge this emergency alert?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'acknowledge';
                
                const alertInput = document.createElement('input');
                alertInput.type = 'hidden';
                alertInput.name = 'alert_id';
                alertInput.value = alertId;
                
                form.appendChild(actionInput);
                form.appendChild(alertInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Simple notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#4caf50' : type === 'error' ? '#e53935' : '#1e88e5'};
                color: white;
                padding: 15px 25px;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                z-index: 10000;
                animation: slideIn 0.3s ease;
                font-weight: 500;
                max-width: 350px;
                display: flex;
                align-items: center;
                gap: 10px;
            `;
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                ${message}
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
        
        function showAssignModal(alertId) {
    const width = 600;
    const height = 700;
    const left = (window.screen.width - width) / 2;
    const top = (window.screen.height - height) / 2;
    
    window.open(`modal_assign_team.php?alert_id=${alertId}`, 
                'assignTeam', 
                `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`);
}

function showAlertDetails(alertId) {
    const width = 950;
    const height = 800;
    const left = (window.screen.width - width) / 2;
    const top = (window.screen.height - height) / 2;
    
    window.open(`modal_view_details.php?alert_id=${alertId}`, 
                'alertDetails', 
                `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`);
}

function acknowledgeAlertComplete(alertId) {
    if (confirm('Mark this emergency as fully acknowledged and completed?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'update_status';
        
        const alertInput = document.createElement('input');
        alertInput.type = 'hidden';
        alertInput.name = 'alert_id';
        alertInput.value = alertId;
        
        const statusInput = document.createElement('input');
        statusInput.type = 'hidden';
        statusInput.name = 'new_status';
        statusInput.value = 'resolved';
        
        const notesInput = document.createElement('input');
        notesInput.type = 'hidden';
        notesInput.name = 'notes';
        notesInput.value = 'Emergency fully acknowledged and completed by ' + new Date().toLocaleString();
        
        form.appendChild(actionInput);
        form.appendChild(alertInput);
        form.appendChild(statusInput);
        form.appendChild(notesInput);
        document.body.appendChild(form);
        form.submit();
    }
}
        // Add CSS for animations
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
    </script>
</body>
</html>

<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo">
      <div class="logo-icon">
        <i class="fas fa-heartbeat"></i>
      </div>
      <div class="logo-text">
        <h2>AuraCare HMS</h2>
      
      </div>
      <ul style="list-style: none; padding: 0; margin: 0;">
         <li>
          <a href="logout.php" class="menu-item logout-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
          </a>
        </li>
      </ul>
    </div>
    
    <button class="sidebar-toggle" id="sidebarToggle">
      <i class="fas fa-bars"></i>
    </button>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section">
      <div class="section-label">Main Menu</div>
      <ul class="sidebar-menu">
        <li>
          <a href="dashboard.php" class="menu-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
            <!-- <span class="menu-badge">New</span> -->
          </a>
        </li>
      </ul>
    </div>

    <div class="nav-section">
      <div class="section-label">Employee Administartion</div>
      <ul class="sidebar-menu">
        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-users"></i>
            <span>Staff Management</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="manage_users.php"><i class="fas fa-user-md"></i>Employee List</a></li>
            <li><a href="departments.php"><i class="fas fa-building"></i>Departments</a></li>
            <li><a href="doctors_schedule.php"><i class="fas fa-calendar-alt"></i>Doctor Schedule</a></li>
            <li><a href="services.php"><i class="fas fa-concierge-bell"></i>Services</a></li>
          </ul>
        </li>
    </div>

    <div class="nav-section">
      <div class="section-label">Patient Care</div>
      <ul class="sidebar-menu"> 
        <li>
          <a href="add_new_patients.php" class="menu-item">
            <i class="fas fa-user-injured"></i>
            <span>Patient Management</span>
          </a>
        </li>
        
        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-calendar-check"></i>
            <span>Appointments</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="appointment_scheduling.php"><i class="fas fa-plus-circle"></i>Schedule Appointment</a></li>
            <li><a href="calendar_view.php"><i class="fas fa-calendar-week"></i>Calendar View</a></li>
            <li><a href="appointment_requests.php"><i class="fas fa-clock"></i>Requests & Approvals</a></li>
          </ul>
        </li>

        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-file-medical"></i>
            <span>EHR System</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="ehr_dashboard.php"><i class="fas fa-tachometer-alt"></i>EHR Dashboard</a></li>
            <li><a href="patient_search.php"><i class="fas fa-search"></i>Patient Search</a></li>
            <li><a href="clinical_notes.php"><i class="fas fa-file-medical-alt"></i>Clinical Notes</a></li>
            <li><a href="lab_orders.php"><i class="fas fa-vial"></i>Lab Orders</a></li>
            <li><a href="prescriptions.php"><i class="fas fa-prescription"></i>Medication Orders</a></li>
            <li><a href="medical_history.php"><i class="fas fa-history"></i>Medical History</a></li>
            <li><a href="vitals_history.php"><i class="fas fa-heartbeat"></i>Vitals History</a></li>
          </ul>
        </li>
      </ul>
    </div>

    <div class="nav-section">
      <div class="section-label">Clinical Modules</div>
      <ul class="sidebar-menu">
        <li>
          <a href="pharmacy.php" class="menu-item">
            <i class="fas fa-capsules"></i>
            <span>Pharmacy</span>
          </a>
        </li>
        
        <li>
          <a href="lab_module.php" class="menu-item">
            <i class="fas fa-vials"></i>
            <span>Laboratory</span>
          </a>
        </li>

        <li>
          <a href="billing_dashboard.php" class="menu-item">
            <i class="fas fa-money-bill-wave"></i>
            <span>Billing & Finance</span>
          </a>
        </li>
        
        <li>
          <a href="wards.php" class="menu-item">
            <i class="fas fa-bed"></i>
            <span>Ward & Bed Management</span>
          </a>
        </li>
      </ul>
    </div>

    <div class="nav-section">
      <div class="section-label">Operations</div>
      <ul class="sidebar-menu">
        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-warehouse"></i>
            <span>Inventory & Supply</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="supplier.php"><i class="fas fa-truck"></i>Manage Suppliers</a></li>
            <li><a href="inventorylist.php"><i class="fas fa-boxes"></i>Manage Inventory</a></li>
          </ul>
        </li>

        <li>
          <a href="reports.php" class="menu-item">
            <i class="fas fa-chart-line"></i>
            <span>Reports</span>
          </a>
        </li>
        
        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-chart-line"></i>
            <span>Reports & Analytics</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="purchase_report.php"><i class="fas fa-shopping-cart"></i>PURCHASE REPORT</a></li>
            <li><a href="bills.php"><i class="fas fa-receipt"></i>SALES REPORT</a></li>
            <li><a href="inventory.php"><i class="fas fa-box-open"></i>STOCK REPORT</a></li>
            <li><a href="supplier_report.php"><i class="fas fa-truck-loading"></i>SUPPLIERS REPORT</a></li>
            <li><a href="return_product_list.php"><i class="fas fa-undo-alt"></i>RETURNS PRODUCTS REPORT</a></li>
            <li><a href="expiry_report.php"><i class="fas fa-calendar-times"></i>EXPIRED PRODUCTS REPORT</a></li>
          </ul>
        </li>
      </ul>
    </div>

    <div class="nav-section">
      <div class="section-label">System</div>
      <ul class="sidebar-menu">
        <li>
          <a href="settings.php" class="menu-item">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
          </a>
        </li>
        <li>
          <a href="logout.php" class="menu-item logout-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
          </a>
        </li>
      </ul>
    </div>
  </nav>

  <div class="sidebar-footer">
    <div class="user-profile">
      <div class="user-avatar">
        <i class="fas fa-user-shield"></i>
      </div>
      <div class="user-info">
        <span class="user-name">Administrator</span>
        <span class="user-role">Super Admin</span>
      </div>
    </div>
  </div>
</aside>

<style>
/* Sidebar Base Styles */
.sidebar {
  position: fixed;
  left: 0;
  top: 0;
  width: 290px;
  height: 100vh;
  background: linear-gradient(180deg, #0c4da2 0%, #07377c 100%);
  color: white;
  overflow-y: auto;
  overflow-x: hidden;
  box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
  z-index: 800;
  transition: all 0.3s ease;
  display: flex;
  flex-direction: column;
}

/* Compact Sidebar */
.sidebar.compact {
  width: 90px;
}

.sidebar.compact .logo-text,
.sidebar.compact .section-label,
.sidebar.compact .menu-item span,
.sidebar.compact .user-info,
.sidebar.compact .menu-badge,
.sidebar.compact .dropdown-arrow {
  display: none !important;
}

.sidebar.compact .submenu {
  display: none !important;
}

/* Custom Scrollbar */
.sidebar::-webkit-scrollbar {
  width: 6px;
}

.sidebar::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 3px;
  transition: background 0.3s ease;
}

.sidebar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}

/* Sidebar Header */
.sidebar-header {
  padding: 1.5rem 1.5rem 1rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.05);
  position: sticky;
  top: 0;
  z-index: 10;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.logo {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.logo-icon {
  width: 40px;
  height: 45px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  flex-shrink: 0;
}

.logo-text h2 {
  margin: 0;
  font-size: 1.6rem;
  font-weight: 900;
  color: white;
  line-height: 1.5;
  letter-spacing: 0.8px;
}

.logo-text small {
  display: block;
  font-size: 1.2rem;
  color: rgba(255, 255, 255, 0.9);
  font-weight: 500;
  margin-top: 0.8rem;
  letter-spacing: 0.3px;
}

.sidebar-toggle {
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: white;
  width: 35px;
  height: 35px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 1rem;
}

.sidebar-toggle:hover {
  background: rgba(255, 255, 255, 0.2);
  transform: rotate(90deg);
}

/* Navigation */
.sidebar-nav {
  flex: 1;
  padding: 1rem 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.nav-section {
  margin-bottom: 1rem;
}

.section-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 0.5rem 1.5rem 0.25rem;
  margin-bottom: 0.25rem;
}

/* Menu List */
.sidebar-menu {
  list-style: none;
  padding: 0;
  margin: 0;
}

.sidebar-menu li {
  margin: 0;
  position: relative;
}

/* Menu Item Styles */
.menu-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1.5rem;
  color: rgba(255, 255, 255, 0.9);
  text-decoration: none;
  transition: all 0.3s ease;
  font-size: 0.9rem;
  font-weight: 500;
  border-left: 3px solid transparent;
  position: relative;
}

.menu-item:hover {
  background: rgba(255, 255, 255, 0.12);
  border-left-color: #4dabf7;
  color: white;
  padding-left: 1.75rem;
}

.menu-item.active {
  background: rgba(255, 255, 255, 0.15);
  border-left-color: #51cf66;
  color: white;
  font-weight: 600;
}

.menu-item i:first-child {
  font-size: 1.1rem;
  width: 20px;
  text-align: center;
  flex-shrink: 0;
}

.menu-item span {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.menu-badge {
  background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
  color: white;
  padding: 0.2rem 0.6rem;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 600;
  min-width: 20px;
  text-align: center;
}

/* Submenu Styles */
.has-submenu > .menu-item {
  position: relative;
}

.dropdown-arrow {
  transition: transform 0.3s ease;
  font-size: 0.8rem;
  color: rgba(255, 255, 255, 0.7);
}

.has-submenu.active .dropdown-arrow {
  transform: rotate(180deg);
  color: white;
}

.submenu {
  list-style: none;
  padding: 0;
  margin: 0;
  background: rgba(0, 0, 0, 0.2);
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.has-submenu.active .submenu {
  max-height: 500px;
}

.submenu li a {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1.5rem 0.75rem 3.5rem;
  color: rgba(255, 255, 255, 0.8);
  text-decoration: none;
  transition: all 0.3s ease;
  font-size: 0.85rem;
  border-left: 3px solid transparent;
}

.submenu li a:hover {
  background: rgba(255, 255, 255, 0.08);
  border-left-color: #ffd43b;
  color: white;
  padding-left: 4rem;
}

.submenu li a i {
  font-size: 0.9rem;
  width: 16px;
  text-align: center;
}

/* Logout Item */
.logout-item {
  color: #ff6b6b !important;
}

.logout-item:hover {
  background: rgba(255, 107, 107, 0.15) !important;
  border-left-color: #ff6b6b !important;
  color: #ff6b6b !important;
}

/* Sidebar Footer */
.sidebar-footer {
  padding: 1.5rem;
  border-top: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.05);
  margin-top: auto;
}

.user-profile {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.user-avatar {
  width: 45px;
  height: 45px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.user-info {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.user-name {
  font-size: 0.9rem;
  font-weight: 600;
  color: white;
  margin-bottom: 0.1rem;
}

.user-role {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.7);
}

/* Main Content Adjustment */
.main-content {
  margin-left: 300px;
  min-height: 100vh;
  padding: 2rem;
  background-color: #f8f9fa;
  transition: margin-left 0.3s ease;
}

.main-content.expanded {
  margin-left: 80px;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .sidebar {
    width: 80px;
    transform: translateX(-100%);
  }
  
  .sidebar.mobile-open {
    transform: translateX(0);
  }
  
  .main-content {
    margin-left: 0;
  }
  
  .mobile-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
  }
  
  .mobile-overlay.active {
    display: block;
  }
}

@media (max-width: 768px) {
  .main-content {
    padding: 1rem;
  }
}

/* Animation for menu items */
@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(-10px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.sidebar-menu li {
  animation: slideIn 0.3s ease forwards;
}

.sidebar-menu li:nth-child(1) { animation-delay: 0.05s; }
.sidebar-menu li:nth-child(2) { animation-delay: 0.1s; }
.sidebar-menu li:nth-child(3) { animation-delay: 0.15s; }
.sidebar-menu li:nth-child(4) { animation-delay: 0.2s; }
.sidebar-menu li:nth-child(5) { animation-delay: 0.25s; }

/* Enhanced hover effects */
.menu-item::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  height: 100%;
  width: 0;
  background: linear-gradient(90deg, rgba(255,255,255,0.1), transparent);
  transition: width 0.3s ease;
}

.menu-item:hover::before {
  width: 100%;
}

/* Status indicators */
.menu-item.active::after {
  content: '';
  position: absolute;
  right: 1rem;
  top: 50%;
  transform: translateY(-50%);
  width: 6px;
  height: 6px;
  background: #51cf66;
  border-radius: 50%;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% { opacity: 1; }
  50% { opacity: 0.5; }
  100% { opacity: 1; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mainContent = document.querySelector('.main-content');
    const currentPath = window.location.pathname.split('/').pop();
    const mobileOverlay = document.createElement('div');
    
    mobileOverlay.className = 'mobile-overlay';
    document.body.appendChild(mobileOverlay);

    // Toggle sidebar compact mode
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('compact');
        mainContent.classList.toggle('expanded');
        
        // Update toggle icon
        const icon = this.querySelector('i');
        if (sidebar.classList.contains('compact')) {
            icon.className = 'fas fa-chevron-right';
        } else {
            icon.className = 'fas fa-bars';
        }
    });

    // Mobile overlay click to close sidebar
    mobileOverlay.addEventListener('click', function() {
        sidebar.classList.remove('mobile-open');
        this.classList.remove('active');
    });

    // Handle dynamic 'active' class on page load
    document.querySelectorAll('.menu-item').forEach(item => {
        const href = item.getAttribute('href');
        if (href && href !== '#' && currentPath === href.split('/').pop()) {
            // Remove active class from all items
            document.querySelectorAll('.menu-item').forEach(i => i.classList.remove('active'));
            document.querySelectorAll('.has-submenu').forEach(i => i.classList.remove('active'));
            
            // Add active class to current item
            item.classList.add('active');
            
            // If it's in a submenu, activate parent
            const parentSubmenu = item.closest('.has-submenu');
            if (parentSubmenu) {
                parentSubmenu.classList.add('active');
            }
        }
    });

    // Handle Submenu Toggle
    document.querySelectorAll('.submenu-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const parentLi = this.closest('.has-submenu');
            
            // Close other submenus
            document.querySelectorAll('.has-submenu.active').forEach(item => {
                if (item !== parentLi) {
                    item.classList.remove('active');
                }
            });
            
            // Toggle current submenu
            parentLi.classList.toggle('active');
        });
    });

    // Close submenus when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024 && !sidebar.contains(e.target)) {
            document.querySelectorAll('.has-submenu.active').forEach(item => {
                item.classList.remove('active');
            });
        }
    });

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            sidebar.classList.remove('mobile-open');
            mobileOverlay.classList.remove('active');
        }
    });

    // Add smooth loading animation
    setTimeout(() => {
        sidebar.style.opacity = '1';
        sidebar.style.transform = 'translateX(0)';
    }, 100);
});

// Mobile menu toggle function
function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.mobile-overlay');
    
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('active');
}

// Initialize sidebar with fade-in effect
window.addEventListener('load', function() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.style.opacity = '0';
    sidebar.style.transform = 'translateX(-20px)';
    
    setTimeout(() => {
        sidebar.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        sidebar.style.opacity = '1';
        sidebar.style.transform = 'translateX(0)';
    }, 300);
});
</script>