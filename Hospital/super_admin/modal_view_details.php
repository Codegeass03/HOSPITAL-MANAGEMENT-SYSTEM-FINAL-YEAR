<?php
session_start();
require_once "includes/db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$alert_id = $_GET['alert_id'] ?? 0;

// Fetch alert details with all related information
$stmt = $pdo->prepare("
    SELECT 
        sa.*,
        p.id as patient_table_id,
        p.patient_id as patient_code,
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        p.age,
        p.gender,
        p.blood_type,
        p.phone as patient_phone,
        p.address as patient_address,
        p.emergency_contact_name,
        p.emergency_contact_phone,
        p.medical_history,
        p.allergies,
        p.current_medications,
        CONCAT(u.first_name, ' ', u.last_name) as assigned_staff_name,
        u.role as staff_role,
        u.phone as staff_phone,
        u.email as staff_email,
        et.team_name,
        et.team_type,
        et.contact_number as team_contact,
        et.vehicle_number,
        et.specializations,
        COUNT(rl.id) as action_count,
        TIMESTAMPDIFF(MINUTE, sa.created_at, NOW()) as minutes_ago,
        DATE_FORMAT(sa.created_at, '%Y-%m-%d %H:%i:%s') as created_at_formatted,
        DATE_FORMAT(sa.responded_at, '%Y-%m-%d %H:%i:%s') as responded_at_formatted,
        DATE_FORMAT(sa.resolved_at, '%Y-%m-%d %H:%i:%s') as resolved_at_formatted
    FROM sos_alerts sa
    LEFT JOIN patients p ON sa.patient_id = p.id
    LEFT JOIN users u ON sa.assigned_to = u.id
    LEFT JOIN emergency_teams et ON sa.response_team_id = et.id
    LEFT JOIN emergency_response_log rl ON sa.id = rl.alert_id
    WHERE sa.id = ?
    GROUP BY sa.id
");
$stmt->execute([$alert_id]);
$alert = $stmt->fetch(PDO::FETCH_ASSOC);

// If no patient data found with the JOIN, try to fetch patient separately
if (!$alert || !$alert['patient_name']) {
    // First get the alert to see what patient_id it has
    $alert_stmt = $pdo->prepare("SELECT * FROM sos_alerts WHERE id = ?");
    $alert_stmt->execute([$alert_id]);
    $alert = $alert_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($alert) {
        // Now fetch patient data separately
        $patient_stmt = $pdo->prepare("
            SELECT id, patient_id as patient_code, 
                   CONCAT(first_name, ' ', last_name) as patient_name,
                   age, gender, blood_type, phone as patient_phone,
                   address as patient_address, emergency_contact_name,
                   emergency_contact_phone, medical_history, allergies,
                   current_medications
            FROM patients 
            WHERE id = ?
        ");
        $patient_stmt->execute([$alert['patient_id']]);
        $patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Merge patient data into alert array
        if ($patient) {
            $alert = array_merge($alert, $patient);
        }
        
        // Fetch assigned staff
        if ($alert['assigned_to']) {
            $staff_stmt = $pdo->prepare("
                SELECT CONCAT(first_name, ' ', last_name) as assigned_staff_name,
                       role as staff_role, phone as staff_phone, email as staff_email
                FROM users WHERE id = ?
            ");
            $staff_stmt->execute([$alert['assigned_to']]);
            $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
            if ($staff) {
                $alert = array_merge($alert, $staff);
            }
        }
        
        // Fetch response team
        if ($alert['response_team_id']) {
            $team_stmt = $pdo->prepare("
                SELECT team_name, team_type, contact_number as team_contact,
                       vehicle_number, specializations
                FROM emergency_teams WHERE id = ?
            ");
            $team_stmt->execute([$alert['response_team_id']]);
            $team = $team_stmt->fetch(PDO::FETCH_ASSOC);
            if ($team) {
                $alert = array_merge($alert, $team);
            }
        }
        
        // Calculate minutes ago
        $alert['minutes_ago'] = floor((time() - strtotime($alert['created_at'])) / 60);
        $alert['created_at_formatted'] = date('Y-m-d H:i:s', strtotime($alert['created_at']));
        $alert['responded_at_formatted'] = $alert['responded_at'] ? date('Y-m-d H:i:s', strtotime($alert['responded_at'])) : null;
        $alert['resolved_at_formatted'] = $alert['resolved_at'] ? date('Y-m-d H:i:s', strtotime($alert['resolved_at'])) : null;
    }
}

// Fetch response log - using created_at instead of performed_at
$log_stmt = $pdo->prepare("
    SELECT rl.*, 
           CONCAT(u.first_name, ' ', u.last_name) as performer_name,
           DATE_FORMAT(rl.created_at, '%Y-%m-%d %H:%i:%s') as performed_at_formatted
    FROM emergency_response_log rl
    LEFT JOIN users u ON rl.performed_by = u.id
    WHERE rl.alert_id = ?
    ORDER BY rl.created_at DESC
");
$log_stmt->execute([$alert_id]);
$response_log = $log_stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Log alert data for troubleshooting
error_log("Alert ID: " . $alert_id);
error_log("Alert Data: " . print_r($alert, true));

// Get status badge class
function getStatusClass($status) {
    $classes = [
        'pending' => 'status-pending',
        'active' => 'status-active',
        'acknowledged' => 'status-acknowledged',
        'responded' => 'status-responded',
        'resolved' => 'status-resolved',
        'cancelled' => 'status-cancelled'
    ];
    return $classes[$status] ?? 'status-pending';
}

// Get priority badge class
function getPriorityClass($priority) {
    $classes = [
        'critical' => 'priority-critical',
        'high' => 'priority-high',
        'medium' => 'priority-medium',
        'low' => 'priority-low'
    ];
    return $classes[$priority] ?? 'priority-medium';
}

// Check if alert exists
if (!$alert) {
    die("Alert not found or you don't have permission to view it.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alert Details | AuraCare HMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Keep all your existing CSS styles */
        :root {
            --primary-blue: #1e88e5;
            --dark-blue: #1565c0;
            --success-green: #4caf50;
            --alert-red: #e53935;
            --warning-orange: #ff9800;
            --white: #ffffff;
            --border: #e0e0e0;
            --light-bg: #f8fafc;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e6f7ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-container {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 900px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* ... rest of your CSS styles remain exactly the same ... */
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
            padding: 25px;
            position: relative;
            flex-shrink: 0;
        }
        
        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        
        .alert-title {
            font-size: 24px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-title i {
            font-size: 28px;
        }
        
        .alert-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .badge {
            padding: 8px 18px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .priority-critical { background: rgba(255, 255, 255, 0.2); color: white; border: 2px solid rgba(255, 255, 255, 0.3); }
        .priority-high { background: rgba(255, 152, 0, 0.2); color: #ff9800; border: 2px solid rgba(255, 152, 0, 0.3); }
        .priority-medium { background: rgba(30, 136, 229, 0.2); color: white; border: 2px solid rgba(255, 255, 255, 0.3); }
        .priority-low { background: rgba(76, 175, 80, 0.2); color: white; border: 2px solid rgba(76, 175, 80, 0.3); }
        
        .status-pending { background: rgba(255, 193, 7, 0.2); color: #ffc107; }
        .status-active { background: rgba(233, 30, 99, 0.2); color: #e91e63; }
        .status-acknowledged { background: rgba(30, 136, 229, 0.2); color: white; }
        .status-responded { background: rgba(76, 175, 80, 0.2); color: white; }
        .status-resolved { background: rgba(0, 150, 136, 0.2); color: #009688; }
        .status-cancelled { background: rgba(158, 158, 158, 0.2); color: #9e9e9e; }
        
        .alert-id {
            font-size: 14px;
            opacity: 0.9;
            background: rgba(255, 255, 255, 0.1);
            padding: 6px 12px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .modal-content {
            padding: 0;
            overflow-y: auto;
            flex: 1;
        }
        
        .content-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            height: 100%;
        }
        
        @media (max-width: 900px) {
            .content-sections {
                grid-template-columns: 1fr;
            }
        }
        
        .section {
            padding: 30px;
            border-bottom: 1px solid var(--border);
        }
        
        .section:last-child {
            border-bottom: none;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            color: var(--dark-blue);
            font-weight: 600;
            font-size: 18px;
        }
        
        .section-header i {
            font-size: 20px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #546e7a;
            font-weight: 500;
            font-size: 14px;
            min-width: 160px;
        }
        
        .info-value {
            color: #263238;
            font-weight: 600;
            text-align: right;
            max-width: 300px;
            word-break: break-word;
        }
        
        .medical-info {
            background: var(--light-bg);
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
            border: 1px solid var(--border);
        }
        
        .medical-title {
            font-weight: 600;
            color: #263238;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .medical-content {
            color: #546e7a;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        
        .log-timeline {
            position: relative;
            padding-left: 30px;
            margin-top: 20px;
        }
        
        .log-timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--primary-blue);
            opacity: 0.3;
        }
        
        .log-item {
            position: relative;
            margin-bottom: 25px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            border: 1px solid var(--border);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .log-item::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 25px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary-blue);
            border: 3px solid white;
            box-shadow: 0 0 0 3px var(--primary-blue);
        }
        
        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .log-action {
            font-weight: 700;
            color: #263238;
            font-size: 15px;
        }
        
        .log-time {
            color: #78909c;
            font-size: 12px;
            background: #f5f5f5;
            padding: 4px 10px;
            border-radius: 12px;
        }
        
        .log-performer {
            color: #546e7a;
            font-size: 13px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .log-details {
            color: #263238;
            font-size: 14px;
            line-height: 1.5;
            padding: 12px;
            background: #f9f9f9;
            border-radius: 6px;
            border-left: 3px solid var(--primary-blue);
        }
        
        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            font-size: 18px;
            z-index: 10;
        }
        
        .close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }
        
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #78909c;
            font-style: italic;
        }
        
        .no-data i {
            font-size: 48px;
            color: #cfd8dc;
            margin-bottom: 20px;
            display: block;
        }
        
        .location-map {
            height: 200px;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 15px;
            border: 1px solid var(--border);
        }
        
        @media (max-width: 600px) {
            .modal-container {
                max-height: 100vh;
                border-radius: 0;
            }
            
            .modal-header {
                padding: 20px;
            }
            
            .alert-title {
                font-size: 20px;
            }
            
            .header-top {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .alert-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .section {
                padding: 20px;
            }
            
            .info-row {
                flex-direction: column;
                gap: 4px;
            }
            
            .info-value {
                text-align: left;
            }
        }
        
        /* Add debug info styles */
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
            color: #856404;
        }
        
        .debug-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="modal-container">
        <div class="modal-header">
            <div class="header-top">
                <div class="alert-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    Emergency Alert Details
                </div>
                <div class="alert-id">
                    <i class="fas fa-hashtag"></i>
                    ALERT-<?php echo str_pad($alert_id, 6, '0', STR_PAD_LEFT); ?>
                </div>
            </div>
            
            <div class="alert-meta">
                <div class="badge <?php echo getPriorityClass($alert['priority']); ?>">
                    <?php echo strtoupper($alert['priority']); ?> PRIORITY
                </div>
                <div class="badge <?php echo getStatusClass($alert['status']); ?>">
                    <?php echo strtoupper($alert['status']); ?>
                </div>
                <div style="color: rgba(255, 255, 255, 0.9); font-size: 14px;">
                    <i class="far fa-clock"></i> <?php echo $alert['minutes_ago'] ?? 0; ?> minutes ago
                </div>
            </div>
            
            <button class="close-btn" onclick="window.close()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-content">
            <div class="content-sections">
                <!-- Left Column -->
                <div>
                    <!-- Patient Information -->
                    <div class="section">
                        <div class="section-header">
                            <i class="fas fa-user-injured"></i>
                            Patient Information
                        </div>
                        <div class="info-grid">
                            <div class="info-row">
                                <span class="info-label">Patient Name:</span>
                                <span class="info-value">
                                    <?php 
                                    if (!empty($alert['patient_name'])) {
                                        echo htmlspecialchars($alert['patient_name']);
                                    } else {
                                        echo '<span style="color: #e53935;">Patient data not found (ID: ' . htmlspecialchars($alert['patient_id'] ?? 'N/A') . ')</span>';
                                    }
                                    ?>
                                </span>
                            </div>
                            <?php if (!empty($alert['patient_code'])): ?>
                            <div class="info-row">
                                <span class="info-label">Patient ID:</span>
                                <span class="info-value"><?php echo htmlspecialchars($alert['patient_code']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($alert['age'])): ?>
                            <div class="info-row">
                                <span class="info-label">Age & Gender:</span>
                                <span class="info-value"><?php echo $alert['age']; ?> years, <?php echo $alert['gender'] ?? 'Not specified'; ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($alert['blood_type'])): ?>
                            <div class="info-row">
                                <span class="info-label">Blood Type:</span>
                                <span class="info-value"><?php echo $alert['blood_type']; ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($alert['patient_phone'])): ?>
                            <div class="info-row">
                                <span class="info-label">Phone:</span>
                                <span class="info-value"><?php echo htmlspecialchars($alert['patient_phone']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($alert['patient_address'])): ?>
                            <div class="info-row">
                                <span class="info-label">Address:</span>
                                <span class="info-value"><?php echo htmlspecialchars($alert['patient_address']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($alert['emergency_contact_name'])): ?>
                            <div class="info-row">
                                <span class="info-label">Emergency Contact:</span>
                                <span class="info-value">
                                    <?php echo htmlspecialchars($alert['emergency_contact_name']); ?><br>
                                    <?php if (!empty($alert['emergency_contact_phone'])): ?>
                                    <small><?php echo htmlspecialchars($alert['emergency_contact_phone']); ?></small>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Medical Information -->
                    <?php if (!empty($alert['medical_history']) || !empty($alert['allergies']) || !empty($alert['current_medications'])): ?>
                    <div class="section">
                        <div class="section-header">
                            <i class="fas fa-file-medical"></i>
                            Medical Information
                        </div>
                        <div class="medical-info">
                            <?php if (!empty($alert['medical_history'])): ?>
                            <div class="medical-title">
                                <i class="fas fa-history"></i> Medical History
                            </div>
                            <div class="medical-content">
                                <?php echo nl2br(htmlspecialchars($alert['medical_history'])); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($alert['allergies'])): ?>
                            <div class="medical-title" style="margin-top: 15px;">
                                <i class="fas fa-allergies"></i> Allergies
                            </div>
                            <div class="medical-content">
                                <?php echo nl2br(htmlspecialchars($alert['allergies'])); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($alert['current_medications'])): ?>
                            <div class="medical-title" style="margin-top: 15px;">
                                <i class="fas fa-pills"></i> Current Medications
                            </div>
                            <div class="medical-content">
                                <?php echo nl2br(htmlspecialchars($alert['current_medications'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Right Column -->
                <div>
                    <!-- Alert Details -->
                    <div class="section">
                        <div class="section-header">
                            <i class="fas fa-info-circle"></i>
                            Alert Details
                        </div>
                        <div class="info-grid">
                            <div class="info-row">
                                <span class="info-label">Emergency Type:</span>
                                <span class="info-value"><?php echo htmlspecialchars($alert['emergency_type'] ?? 'Medical Emergency'); ?></span>
                            </div>
                            <?php if (!empty($alert['medical_summary'])): ?>
                            <div class="info-row">
                                <span class="info-label">Medical Summary:</span>
                                <span class="info-value"><?php echo nl2br(htmlspecialchars($alert['medical_summary'])); ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <span class="info-label">Location:</span>
                                <span class="info-value">
                                    <?php if (!empty($alert['latitude']) && !empty($alert['longitude'])): ?>
                                        <?php echo number_format($alert['latitude'], 4); ?>, <?php echo number_format($alert['longitude'], 4); ?>
                                        <?php if (!empty($alert['location_address'])): ?>
                                            <br><small><?php echo htmlspecialchars($alert['location_address']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #78909c;">Location not available</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Created:</span>
                                <span class="info-value"><?php echo $alert['created_at_formatted'] ?? date('Y-m-d H:i:s', strtotime($alert['created_at'])); ?></span>
                            </div>
                            <?php if (!empty($alert['responded_at_formatted'])): ?>
                            <div class="info-row">
                                <span class="info-label">Responded:</span>
                                <span class="info-value"><?php echo $alert['responded_at_formatted']; ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($alert['resolved_at_formatted'])): ?>
                            <div class="info-row">
                                <span class="info-label">Resolved:</span>
                                <span class="info-value"><?php echo $alert['resolved_at_formatted']; ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Response Information -->
                    <div class="section">
                        <div class="section-header">
                            <i class="fas fa-user-md"></i>
                            Response Information
                        </div>
                        <div class="info-grid">
                            <?php if (!empty($alert['assigned_staff_name'])): ?>
                            <div class="info-row">
                                <span class="info-label">Assigned Staff:</span>
                                <span class="info-value">
                                    <?php echo htmlspecialchars($alert['assigned_staff_name']); ?><br>
                                    <?php if (!empty($alert['staff_role'])): ?>
                                    <small><?php echo $alert['staff_role']; ?></small>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if (!empty($alert['staff_phone'])): ?>
                            <div class="info-row">
                                <span class="info-label">Staff Phone:</span>
                                <span class="info-value"><?php echo htmlspecialchars($alert['staff_phone']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if (!empty($alert['team_name'])): ?>
                            <div class="info-row">
                                <span class="info-label">Response Team:</span>
                                <span class="info-value">
                                    <?php echo htmlspecialchars($alert['team_name']); ?><br>
                                    <?php if (!empty($alert['team_type'])): ?>
                                    <small><?php echo ucfirst($alert['team_type']); ?> Team</small>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if (!empty($alert['team_contact'])): ?>
                            <div class="info-row">
                                <span class="info-label">Team Contact:</span>
                                <span class="info-value"><?php echo htmlspecialchars($alert['team_contact']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($alert['vehicle_number'])): ?>
                            <div class="info-row">
                                <span class="info-label">Vehicle Number:</span>
                                <span class="info-value"><?php echo htmlspecialchars($alert['vehicle_number']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if (!empty($alert['specializations'])): ?>
                            <div class="info-row">
                                <span class="info-label">Specializations:</span>
                                <span class="info-value"><?php echo htmlspecialchars($alert['specializations']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Response Log -->
                    <div class="section">
                        <div class="section-header">
                            <i class="fas fa-history"></i>
                            Response Timeline
                        </div>
                        <?php if (!empty($response_log)): ?>
                            <div class="log-timeline">
                                <?php foreach ($response_log as $log): ?>
                                    <div class="log-item">
                                        <div class="log-header">
                                            <div class="log-action">
                                                <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                            </div>
                                            <div class="log-time">
                                                <?php echo $log['performed_at_formatted']; ?>
                                            </div>
                                        </div>
                                        <div class="log-performer">
                                            <i class="fas fa-user"></i>
                                            <?php echo htmlspecialchars($log['performer_name'] ?? 'System'); ?>
                                        </div>
                                        <?php if (!empty($log['details'])): ?>
                                        <div class="log-details">
                                            <?php echo htmlspecialchars($log['details']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-history"></i>
                                <p>No response actions recorded yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Close window on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.close();
            }
        });
        
        // Debug: Log alert data to console
        console.log("Alert Data:", <?php echo json_encode($alert); ?>);
    </script>
</body>
</html>