<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
include "admin/connection.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

date_default_timezone_set('Africa/Nairobi');

// Get the logged-in nurse's ID
$nurse_id = $_SESSION['user_id'] ?? null;

if (!$nurse_id) {
    die("Nurse not logged in.");
}

// Fetch Nurse Information
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.department
        FROM users u
        WHERE u.id = ? AND u.role = 'Nurse'
    ");
    $stmt->execute([$nurse_id]);
    $nurse = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$nurse) {
        die("Nurse not found.");
    }
} catch (PDOException $e) {
    die("Error fetching nurse information: " . $e->getMessage());
}

// Fetch Dashboard Statistics for Nurses
$stats = [];

// 1. Patients Requiring Triage Today - ONLY for patients with scheduled appointments
try {
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT a.patient_id) as count 
        FROM appointments a
        LEFT JOIN triage_assessments ta ON a.patient_id = ta.patient_id AND DATE(ta.assessment_date) = CURDATE()
        WHERE DATE(a.appointment_date) = CURDATE()
        AND a.status IN ('scheduled', 'confirmed')
        AND ta.id IS NULL
    ");
    $stats['patients_needing_triage'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
} catch (PDOException $e) {
    $stats['patients_needing_triage'] = 0;
}

// 2. Current Admissions in Nurse's Department
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM bed_assignments ba
        JOIN beds b ON ba.bed_id = b.id
        JOIN ward w ON b.ward_id = w.id
        WHERE ba.status = 'active'
        AND w.department = ?
    ");
    $stmt->execute([$nurse['department']]);
    $stats['current_admissions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['current_admissions'] = 0;
}

// 3. Vital Signs Taken Today
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM patient_vitals 
        WHERE DATE(recorded_at) = CURDATE()
        AND recorded_by = ?
    ");
    $stmt->execute([$nurse_id]);
    $stats['vitals_taken_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['vitals_taken_today'] = 0;
}

// 4. Pending Medication Administration
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM patient_medications 
        WHERE status = 'active'
        AND start_date <= CURDATE()
        AND (end_date IS NULL OR end_date >= CURDATE())
    ");
    $stats['pending_medications'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['pending_medications'] = 0;
}

// 5. Emergency Cases Today - Get from SOS alerts or emergency appointments
try {
    // Count active SOS alerts for today
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM sos_alerts 
        WHERE status = 'active'
        AND DATE(created_at) = CURDATE()
    ");
    $sos_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Also count emergency appointments for today
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE DATE(appointment_date) = CURDATE()
        AND appointment_type = 'emergency'
        AND status IN ('scheduled', 'confirmed', 'in_progress')
    ");
    $emergency_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $stats['emergency_cases'] = $sos_count + $emergency_appointments;
} catch (PDOException $e) {
    $stats['emergency_cases'] = 0;
}

// 6. Lab Results Awaiting Review
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM lab_orders 
        WHERE status = 'completed'
        AND DATE(completed_at) = CURDATE()
    ");
    $stats['lab_results_pending'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['lab_results_pending'] = 0;
}

// Patients Needing Triage - ONLY those with scheduled appointments
$patients_needing_triage = [];
try {
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.patient_id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.age,
            p.gender,
            p.phone,
            a.appointment_time,
            a.appointment_number,
            pv.temperature,
            pv.blood_pressure_systolic,
            pv.blood_pressure_diastolic,
            pv.heart_rate
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        LEFT JOIN triage_assessments ta ON p.id = ta.patient_id AND DATE(ta.assessment_date) = CURDATE()
        LEFT JOIN patient_vitals pv ON p.id = pv.patient_id AND DATE(pv.recorded_at) = CURDATE()
        WHERE DATE(a.appointment_date) = CURDATE()
        AND a.status IN ('scheduled', 'confirmed')
        AND ta.id IS NULL
        ORDER BY a.appointment_time ASC
        LIMIT 8
    ");
    $patients_needing_triage = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $patients_needing_triage = [];
}

// Current Admissions in Nurse's Department
$current_admissions = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.patient_id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.age,
            p.gender,
            ba.admission_date,
            ba.reason_for_admission,
            b.bed_number,
            w.ward_name,
            pv.temperature,
            pv.blood_pressure_systolic,
            pv.blood_pressure_diastolic
        FROM bed_assignments ba
        JOIN patients p ON ba.patient_id = p.id
        JOIN beds b ON ba.bed_id = b.id
        JOIN ward w ON b.ward_id = w.id
        LEFT JOIN patient_vitals pv ON p.id = pv.patient_id AND DATE(pv.recorded_at) = CURDATE()
        WHERE ba.status = 'active'
        AND w.department = ?
        ORDER BY ba.admission_date DESC
        LIMIT 6
    ");
    $stmt->execute([$nurse['department']]);
    $current_admissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $current_admissions = [];
}

// Pending Medications
$pending_medications = [];
try {
    $stmt = $pdo->query("
        SELECT 
            pm.id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id,
            pm.medication_name,
            pm.dosage,
            pm.frequency,
            pm.route,
            pm.instructions,
            pm.start_date
        FROM patient_medications pm
        JOIN patients p ON pm.patient_id = p.id
        WHERE pm.status = 'active'
        AND pm.start_date <= CURDATE()
        AND (pm.end_date IS NULL OR pm.end_date >= CURDATE())
        ORDER BY pm.start_date ASC
        LIMIT 6
    ");
    $pending_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pending_medications = [];
}

// Recent Vital Signs (Last 2 hours)
$recent_vitals = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            pv.id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id,
            pv.temperature,
            pv.blood_pressure_systolic,
            pv.blood_pressure_diastolic,
            pv.heart_rate,
            pv.oxygen_saturation,
            pv.recorded_at
        FROM patient_vitals pv
        JOIN patients p ON pv.patient_id = p.id
        WHERE pv.recorded_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        AND pv.recorded_by = ?
        ORDER BY pv.recorded_at DESC
        LIMIT 8
    ");
    $stmt->execute([$nurse_id]);
    $recent_vitals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_vitals = [];
}

// Emergency Cases Today - Get from SOS alerts
$emergency_cases = [];
try {
    $stmt = $pdo->query("
        SELECT 
            s.id,
            s.patient_id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id as patient_identifier,
            s.emergency_type,
            s.priority,
            s.status,
            s.created_at,
            COALESCE(u.department, 'Unassigned') as assigned_dept
        FROM sos_alerts s
        LEFT JOIN patients p ON s.patient_id = p.id
        LEFT JOIN users u ON s.assigned_to = u.id
        WHERE s.status = 'active'
        AND DATE(s.created_at) = CURDATE()
        ORDER BY 
            CASE s.priority 
                WHEN 'critical' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
            END,
            s.created_at ASC
        LIMIT 5
    ");
    $emergency_cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $emergency_cases = [];
}

// Department Activity Stats
$department_activity = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            'Vitals' as activity_type,
            COUNT(*) as count
        FROM patient_vitals 
        WHERE DATE(recorded_at) = CURDATE()
        AND recorded_by IN (SELECT id FROM users WHERE department = ?)
        
        UNION ALL
        
        SELECT 
            'Triage' as activity_type,
            COUNT(*) as count
        FROM triage_assessments 
        WHERE DATE(assessment_date) = CURDATE()
        AND nurse_id IN (SELECT id FROM users WHERE department = ?)
        
        UNION ALL
        
        SELECT 
            'Medications' as activity_type,
            COUNT(*) as count
        FROM patient_medications 
        WHERE DATE(created_at) = CURDATE()
        AND prescribing_doctor IN (SELECT id FROM users WHERE department = ?)
    ");
    $stmt->execute([$nurse['department'], $nurse['department'], $nurse['department']]);
    $department_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $department_activity = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nurse Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
        }

        .dashboard-container {
            padding: 40px;
            margin-left: 260px;
            max-width: 20000px;
        }

        .dashboard-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nurse-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .nurse-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }

        .nurse-details h1 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .nurse-details p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .dashboard-header p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .stat-card.sos-alert {
            animation: sosPulse 2s infinite;
            border-left: 4px solid #f44336;
        }

        @keyframes sosPulse {
            0% { box-shadow: 0 2px 8px rgba(244, 67, 54, 0.3); }
            50% { box-shadow: 0 2px 15px rgba(244, 67, 54, 0.6); }
            100% { box-shadow: 0 2px 8px rgba(244, 67, 54, 0.3); }
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.green { background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); }
        .stat-icon.orange { background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%); }
        .stat-icon.red { background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%); }
        .stat-icon.purple { background: linear-gradient(135deg, #9c27b0 0%, #7b1fa2 100%); }
        .stat-icon.teal { background: linear-gradient(135deg, #009688 0%, #00796b 100%); }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
            font-weight: 500;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .data-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .data-card h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e9ecef;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-critical { background: #f44336; color: white; }
        .status-high { background: #ff9800; color: white; }
        .status-medium { background: #ffc107; color: #333; }
        .status-low { background: #4caf50; color: white; }
        .status-scheduled { background: #e3f2fd; color: #1976d2; }
        .status-confirmed { background: #e8f5e9; color: #388e3c; }
        .status-emergency { background: #ffcdd2; color: #c62828; }

        .vital-sign {
            font-family: monospace;
            font-weight: 600;
            font-size: 13px;
        }

        .vital-normal { color: #388e3c; }
        .vital-warning { color: #ff9800; }
        .vital-critical { color: #f44336; }

        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-size: 24px;
            transition: transform 0.3s;
        }

        .refresh-btn:hover {
            transform: scale(1.1);
        }

        .no-data {
            text-align: center;
            color: #7f8c8d;
            font-style: italic;
            padding: 40px 0;
        }

        .action-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            font-size: 13px;
        }

        .action-link:hover {
            text-decoration: underline;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .quick-action-btn {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: #333;
        }

        .quick-action-btn:hover {
            background: #f8f9fa;
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .quick-action-btn i {
            font-size: 24px;
            color: #667eea;
            margin-bottom: 8px;
            display: block;
        }

        .patient-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .patient-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                margin-left: 0;
                padding: 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="nurse-info">
                <div class="nurse-avatar">
                    <?php 
                        $initials = substr($nurse['first_name'], 0, 1) . substr($nurse['last_name'], 0, 1);
                        echo strtoupper($initials);
                    ?>
                </div>
                <div class="nurse-details">
                    <h1><?php echo htmlspecialchars($nurse['first_name'] . ' ' . $nurse['last_name']); ?></h1>
                    <p>Nurse • <?php echo htmlspecialchars($nurse['department']); ?></p>
                </div>
            </div>
            <p>Last updated: <?php echo date('F j, Y g:i A'); ?></p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='appointment_scheduling.php'">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['patients_needing_triage']; ?></div>
                        <div class="stat-label">Need Triage</div>
                        <div style="font-size: 12px; color: #7f8c8d; margin-top: 5px;">
                            <i class="fas fa-calendar-check"></i> With scheduled appointments
                        </div>
                    </div>
                    <div class="stat-icon red">
                        <i class="fas fa-stethoscope"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card" onclick="window.location.href='wards.php'">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['current_admissions']; ?></div>
                        <div class="stat-label">Admissions</div>
                    </div>
                    <div class="stat-icon blue">
                        <i class="fas fa-bed"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card" onclick="window.location.href='vitals_history.php'">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['vitals_taken_today']; ?></div>
                        <div class="stat-label">Vitals Today</div>
                    </div>
                    <div class="stat-icon green">
                        <i class="fas fa-heartbeat"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card <?php echo $stats['emergency_cases'] > 0 ? 'sos-alert' : ''; ?>" onclick="window.location.href='sos_portal.php'">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['emergency_cases']; ?></div>
                        <div class="stat-label">Emergency Cases</div>
                        <div style="font-size: 12px; color: #f44336; margin-top: 5px;">
                            <i class="fas fa-first-aid"></i> Link to SOS Portal
                        </div>
                    </div>
                    <div class="stat-icon purple">
                        <i class="fas fa-first-aid"></i>
                    </div>
                </div>
            </div>

            <!-- <div class="stat-card" onclick="window.location.href='lab_results.php'">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['lab_results_pending']; ?></div>
                        <div class="stat-label">Lab Results</div>
                    </div>
                    <div class="stat-icon teal">
                        <i class="fas fa-flask"></i>
                    </div>
                </div>
            </div> -->
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Left Column -->
            <div>
                <!-- Patients Needing Triage -->
                <div class="data-card">
                    <h3><i class="fas fa-stethoscope"></i> Patients Needing Triage (Today's Appointments)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Age/Gender</th>
                                <th>Appointment Time</th>
                                <th>Last Vitals</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($patients_needing_triage) > 0): ?>
                                <?php foreach ($patients_needing_triage as $patient): ?>
                                    <tr onclick="window.location.href='appointment_scheduling.php?patient_id=<?php echo $patient['id']; ?>&appointment_id=<?php echo $patient['appointment_number']; ?>'" style="cursor: pointer;">
                                        <td>
                                            <div class="patient-info">
                                                <div class="patient-avatar">
                                                    <?php 
                                                        $initials = substr($patient['patient_name'], 0, 1);
                                                        echo strtoupper($initials);
                                                    ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($patient['patient_name']); ?></strong>
                                                    <br><small><?php echo htmlspecialchars($patient['patient_id']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo $patient['age']; ?> / <?php echo $patient['gender']; ?></td>
                                        <td><?php echo date('H:i', strtotime($patient['appointment_time'])); ?></td>
                                        <td>
                                            <?php if ($patient['temperature']): ?>
                                                <span class="vital-sign vital-normal">
                                                    <?php echo $patient['temperature']; ?>°C • 
                                                    <?php echo $patient['blood_pressure_systolic']; ?>/<?php echo $patient['blood_pressure_diastolic']; ?> • 
                                                    <?php echo $patient['heart_rate']; ?> BPM
                                                </span>
                                            <?php else: ?>
                                                <em>No vitals recorded</em>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #7f8c8d;">No patients with appointments need triage today</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div style="text-align: right; margin-top: 10px;">
                        <a href="appointment_scheduling.php" class="action-link">View All Triage →</a>
                    </div>
                </div>

                <!-- Current Admissions -->
                <div class="data-card">
                    <h3><i class="fas fa-bed"></i> Current Admissions</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Bed</th>
                                <th>Admission Reason</th>
                                <th>Vitals</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($current_admissions) > 0): ?>
                                <?php foreach ($current_admissions as $admission): ?>
                                    <tr>
                                        <td>
                                            <div class="patient-info">
                                                <div class="patient-avatar">
                                                    <?php 
                                                        $initials = substr($admission['patient_name'], 0, 1);
                                                        echo strtoupper($initials);
                                                    ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($admission['patient_name']); ?></strong>
                                                    <br><small><?php echo $admission['age']; ?> / <?php echo $admission['gender']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($admission['bed_number']); ?></strong>
                                            <br><small><?php echo htmlspecialchars($admission['ward_name']); ?></small>
                                        </td>
                                        <td><?php echo strlen($admission['reason_for_admission']) > 30 ? substr($admission['reason_for_admission'], 0, 30) . '...' : $admission['reason_for_admission']; ?></td>
                                        <td>
                                            <?php if ($admission['temperature']): ?>
                                                <span class="vital-sign vital-normal">
                                                    <?php echo $admission['temperature']; ?>°C • 
                                                    <?php echo $admission['blood_pressure_systolic']; ?>/<?php echo $admission['blood_pressure_diastolic']; ?>
                                                </span>
                                            <?php else: ?>
                                                <a href="vitals_history.php?patient_id=<?php echo $admission['id']; ?>" class="action-link">Take Vitals</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #7f8c8d;">No current admissions</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div style="text-align: right; margin-top: 10px;">
                        <a href="patients.php" class="action-link">View All Patients →</a>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Quick Actions -->
                <div class="data-card">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                    <div class="quick-actions">
                        <!-- <a href="appointment_Scheduling.php" class="quick-action-btn">
                            <i class="fas fa-stethoscope"></i>
                            <div>New Triage</div>
                        </a> -->
                        <a href="appointment_Scheduling.php" class="quick-action-btn">
                            <i class="fas fa-heartbeat"></i>
                            <div>Record Vitals</div>
                        </a>
                        <!-- <a href="medications.php" class="quick-action-btn">
                            <i class="fas fa-pills"></i>
                            <div>Medications</div>
                        </a> -->
                        <a href="sos_portal.php" class="quick-action-btn">
                            <i class="fas fa-first-aid"></i>
                            <div>SOS Portal</div>
                        </a>
                    </div>
                </div>

                <!-- Recent Vital Signs -->
                <div class="data-card">
                    <h3><i class="fas fa-heartbeat"></i> Recent Vital Signs</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Vitals</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recent_vitals) > 0): ?>
                                <?php foreach ($recent_vitals as $vital): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($vital['patient_name']); ?></strong>
                                            <br><small><?php echo htmlspecialchars($vital['patient_id']); ?></small>
                                        </td>
                                        <td>
                                            <span class="vital-sign vital-normal">
                                                T: <?php echo $vital['temperature']; ?>°C • 
                                                BP: <?php echo $vital['blood_pressure_systolic']; ?>/<?php echo $vital['blood_pressure_diastolic']; ?> • 
                                                HR: <?php echo $vital['heart_rate']; ?> • 
                                                O2: <?php echo $vital['oxygen_saturation']; ?>%
                                            </span>
                                        </td>
                                        <td><?php echo date('H:i', strtotime($vital['recorded_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: #7f8c8d;">No recent vital signs</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div style="text-align: right; margin-top: 10px;">
                        <a href="vitals_history.php" class="action-link">View All Vitals →</a>
                    </div>
                </div>

                <!-- Emergency Cases -->
                <div class="data-card">
                    <h3><i class="fas fa-first-aid"></i> Emergency SOS Alerts</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Emergency Type</th>
                                <th>Priority</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($emergency_cases) > 0): ?>
                                <?php foreach ($emergency_cases as $emergency): ?>
                                    <tr onclick="window.location.href='sos_portal.php?alert_id=<?php echo $emergency['id']; ?>'" style="cursor: pointer;">
                                        <td>
                                            <strong><?php echo htmlspecialchars($emergency['patient_name']); ?></strong>
                                            <br><small><?php echo htmlspecialchars($emergency['patient_identifier']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($emergency['emergency_type']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($emergency['priority']); ?>">
                                                <?php echo ucfirst($emergency['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="sos_portal.php?alert_id=<?php echo $emergency['id']; ?>" class="action-link">Respond</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #7f8c8d;">No active emergency alerts</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div style="text-align: right; margin-top: 10px;">
                        <a href="sos_portal.php" class="action-link">Go to SOS Portal →</a>
                    </div>
                </div>

                <!-- Pending Medications -->
                <!-- <div class="data-card">
                    <h3><i class="fas fa-pills"></i> Pending Medications</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Medication</th>
                                <th>Dosage</th>
                                <th>Frequency</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($pending_medications) > 0): ?>
                                <?php foreach ($pending_medications as $med): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($med['patient_name']); ?></strong>
                                            <br><small><?php echo htmlspecialchars($med['patient_id']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($med['medication_name']); ?></td>
                                        <td><?php echo htmlspecialchars($med['dosage']); ?></td>
                                        <td><?php echo htmlspecialchars($med['frequency']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #7f8c8d;">No pending medications</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div style="text-align: right; margin-top: 10px;">
                        <a href="medications.php" class="action-link">View All Medications →</a>
                    </div>
                </div> -->
            </div>
        </div>
    </div>

    <!-- Refresh Button -->
    <button class="refresh-btn" onclick="location.reload();" title="Refresh Dashboard">
        <i class="fas fa-sync-alt"></i>
    </button>

    <script>
        // Auto-refresh every 2 minutes
        setTimeout(function() {
            location.reload();
        }, 120000);
        
        // Check for new emergency alerts every 30 seconds
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
                background: ${type === 'success' ? '#4caf50' : '#f44336'};
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
    </script>
</body>
</html>