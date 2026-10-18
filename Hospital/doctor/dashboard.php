<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
include "admin/connection.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

date_default_timezone_set('Africa/Nairobi');

// Get the logged-in doctor's ID
$doctor_id = $_SESSION['user_id'] ?? null;

if (!$doctor_id) {
    die("Doctor not logged in.");
}
// Fetch Doctor Information
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.specialization, d.name as department_name
        FROM users u
        LEFT JOIN departments d ON u.department COLLATE utf8mb4_unicode_ci = d.name
        WHERE u.id = ? AND u.role = 'Doctor'
    ");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor) {
        die("Doctor not found.");
    }
} catch (PDOException $e) {
    die("Error fetching doctor information: " . $e->getMessage());
}


// Fetch Dashboard Statistics for this Doctor
$stats = [];

// 1. Today's Appointments for this Doctor
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE doctor_id = ? 
        AND DATE(appointment_date) = CURDATE() 
        AND status IN ('scheduled', 'confirmed', 'in_progress')
    ");
    $stmt->execute([$doctor_id]);
    $stats['today_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['today_appointments'] = 0;
}

// 2. Doctor's Active Patients (patients with appointments in last 30 days)
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT patient_id) as count 
        FROM appointments 
        WHERE doctor_id = ? 
        AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$doctor_id]);
    $stats['my_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['my_patients'] = 0;
}

// 3. Pending Lab Results for Doctor's Patients
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT lo.id) as count 
        FROM lab_orders lo
        JOIN appointments a ON lo.patient_id = a.patient_id
        WHERE a.doctor_id = ? 
        AND lo.status IN ('ordered', 'in_progress')
        AND lo.order_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$doctor_id]);
    $stats['pending_labs'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['pending_labs'] = 0;
}

// 4. Today's Completed Consultations
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE doctor_id = ? 
        AND DATE(appointment_date) = CURDATE() 
        AND status = 'completed'
    ");
    $stmt->execute([$doctor_id]);
    $stats['completed_consultations'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['completed_consultations'] = 0;
}

// 5. Upcoming Appointments (next 7 days)
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE doctor_id = ? 
        AND appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND status IN ('scheduled', 'confirmed')
    ");
    $stmt->execute([$doctor_id]);
    $stats['upcoming_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['upcoming_appointments'] = 0;
}

// 6. Prescriptions Written Today
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM prescriptions 
        WHERE doctor_id = ? 
        AND DATE(prescription_date) = CURDATE()
    ");
    $stmt->execute([$doctor_id]);
    $stats['prescriptions_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['prescriptions_today'] = 0;
}

// Recent Appointments for this Doctor
$recent_appointments = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.appointment_number,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id,
            a.appointment_date,
            a.appointment_time,
            a.status,
            a.appointment_type,
            a.reason
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.doctor_id = ?
        AND a.appointment_date >= CURDATE()
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 8
    ");
    $stmt->execute([$doctor_id]);
    $recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_appointments = [];
}

// Pending Lab Orders for Doctor's Patients
$pending_lab_orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            lo.id,
            lo.order_number,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id,
            lo.order_date,
            lo.priority,
            lo.status,
            GROUP_CONCAT(loi.test_name SEPARATOR ', ') as tests_requested
        FROM lab_orders lo
        JOIN patients p ON lo.patient_id = p.id
        LEFT JOIN lab_order_items loi ON lo.id = loi.lab_order_id
        WHERE lo.id IN (
            SELECT DISTINCT lo2.id 
            FROM lab_orders lo2
            JOIN appointments a ON lo2.patient_id = a.patient_id
            WHERE a.doctor_id = ?
        )
        AND lo.status IN ('ordered', 'in_progress')
        GROUP BY lo.id
        ORDER BY 
            CASE lo.priority 
                WHEN 'stat' THEN 1
                WHEN 'urgent' THEN 2
                ELSE 3 
            END,
            lo.order_date ASC
        LIMIT 6
    ");
    $stmt->execute([$doctor_id]);
    $pending_lab_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pending_lab_orders = [];
}

// Recent Clinical Notes by this Doctor
$recent_notes = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            cn.id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id,
            cn.created_at,
            LEFT(cn.subjective, 50) as subjective_preview,
            cn.diagnosis
        FROM clinical_notes cn
        JOIN patients p ON cn.patient_id = p.id
        WHERE cn.doctor_id = ?
        ORDER BY cn.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$doctor_id]);
    $recent_notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_notes = [];
}

// Appointment Statistics by Type (Last 30 days)
$appointment_stats = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            appointment_type,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)), 1) as percentage
        FROM appointments 
        WHERE doctor_id = ? 
        AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY appointment_type
        ORDER BY count DESC
    ");
    $stmt->execute([$doctor_id, $doctor_id]);
    $appointment_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $appointment_stats = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard</title>
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

        .doctor-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .doctor-avatar {
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

        .doctor-details h1 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .doctor-details p {
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
            grid-template-columns: 2fr 1fr;
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

        .status-scheduled { background: #e3f2fd; color: #1976d2; }
        .status-confirmed { background: #e8f5e9; color: #388e3c; }
        .status-completed { background: #f3e5f5; color: #7b1fa2; }
        .status-cancelled { background: #ffebee; color: #c62828; }
        .status-ordered { background: #fff3e0; color: #ef6c00; }
        .status-in_progress { background: #e1f5fe; color: #0288d1; }

        .priority-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            display: inline-block;
        }

        .priority-stat { background: #ffcdd2; color: #c62828; }
        .priority-urgent { background: #ffe0b2; color: #ef6c00; }
        .priority-routine { background: #c8e6c9; color: #388e3c; }

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
            <div class="doctor-info">
                <div class="doctor-avatar">
                    <?php 
                        $initials = substr($doctor['first_name'], 0, 1) . substr($doctor['last_name'], 0, 1);
                        echo strtoupper($initials);
                    ?>
                </div>
                <div class="doctor-details">
                    <h1>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h1>
                    <p><?php echo htmlspecialchars($doctor['department_name']); ?> • <?php echo htmlspecialchars($doctor['specialization']); ?></p>
                </div>
            </div>
            <p>Last updated: <?php echo date('F j, Y g:i A'); ?></p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='appointment_scheduling.php'">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['today_appointments']; ?></div>
                        <div class="stat-label">Today's Appointments</div>
                    </div>
                    <div class="stat-icon blue">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
            </div>

            <!-- <div class="stat-card" onclick="window.location.href='patients.php'">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['my_patients']; ?></div>
                        <div class="stat-label">My Patients</div>
                    </div>
                    <div class="stat-icon green">
                        <i class="fas fa-user-injured"></i>
                    </div>
                </div>
            </div> -->

            <div class="stat-card" onclick="window.location.href='lab_orders.php'">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['pending_labs']; ?></div>
                        <div class="stat-label">Pending Lab Results</div>
                    </div>
                    <div class="stat-icon orange">
                        <i class="fas fa-flask"></i>
                    </div>
                </div>
            </div>

            <!-- <div class="stat-card" onclick="window.location.href='appointments.php?filter=completed'">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['completed_consultations']; ?></div>
                        <div class="stat-label">Completed Today</div>
                    </div>
                    <div class="stat-icon red">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div> -->

            <div class="stat-card" onclick="window.location.href='appointment_scheduling.php'">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['upcoming_appointments']; ?></div>
                        <div class="stat-label">Next 7 Days</div>
                    </div>
                    <div class="stat-icon purple">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card" onclick="window.location.href='prescriptions.php'">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['prescriptions_today']; ?></div>
                        <div class="stat-label">Prescriptions Today</div>
                    </div>
                    <div class="stat-icon teal">
                        <i class="fas fa-prescription"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Left Column -->
            <div>
                <!-- Today's Appointments -->
                <div class="data-card">
                    <h3><i class="fas fa-calendar-day"></i> Today's Appointments</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Reason</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recent_appointments) > 0): ?>
                                <?php foreach ($recent_appointments as $apt): ?>
                                    <?php if (date('Y-m-d', strtotime($apt['appointment_date'])) == date('Y-m-d')): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($apt['patient_name']); ?></strong>
                                                <br><small><?php echo htmlspecialchars($apt['patient_id']); ?></small>
                                            </td>
                                            <td><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></td>
                                            <td><?php echo ucfirst($apt['appointment_type']); ?></td>
                                            <td><?php echo strlen($apt['reason']) > 30 ? substr($apt['reason'], 0, 30) . '...' : $apt['reason']; ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $apt['status']; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $apt['status'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (count(array_filter($recent_appointments, function($apt) { return date('Y-m-d', strtotime($apt['appointment_date'])) == date('Y-m-d'); })) == 0): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: #7f8c8d;">No appointments today</td>
                                    </tr>
                                <?php endif; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #7f8c8d;">No upcoming appointments</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div style="text-align: right; margin-top: 10px;">
                        <a href="appointment_scheduling.php" class="action-link">View All Appointments →</a>
                    </div>
                </div>

                <!-- Pending Lab Results -->
                <div class="data-card">
                    <h3><i class="fas fa-vial"></i> Pending Lab Results</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Order Date</th>
                                <th>Tests</th>
                                <th>Priority</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($pending_lab_orders) > 0): ?>
                                <?php foreach ($pending_lab_orders as $lab): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($lab['patient_name']); ?></strong>
                                            <br><small><?php echo htmlspecialchars($lab['patient_id']); ?></small>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($lab['order_date'])); ?></td>
                                        <td><?php echo strlen($lab['tests_requested']) > 30 ? substr($lab['tests_requested'], 0, 30) . '...' : $lab['tests_requested']; ?></td>
                                        <td>
                                            <span class="priority-badge priority-<?php echo $lab['priority']; ?>">
                                                <?php echo ucfirst($lab['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $lab['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $lab['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #7f8c8d;">No pending lab results</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div style="text-align: right; margin-top: 10px;">
                        <a href="lab_orders.php" class="action-link">View All Lab Orders →</a>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Quick Actions -->
                <div class="data-card">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                    <div class="quick-actions">
                        <a href="appointment_scheduling.php" class="quick-action-btn">
                            <i class="fas fa-calendar-plus"></i>
                            <div>New Appointment</div>
                        </a>
                        <a href="prescriptions.php" class="quick-action-btn">
                            <i class="fas fa-prescription"></i>
                            <div>Write Prescription</div>
                        </a>
                        <a href="lab_orders.php?action=new" class="quick-action-btn">
                            <i class="fas fa-vial"></i>
                            <div>Order Lab Test</div>
                        </a>
                        <a href="clinical_notes.php" class="quick-action-btn">
                            <i class="fas fa-notes-medical"></i>
                            <div>Clinical Notes</div>
                        </a>
                    </div>
                </div>

                <!-- Recent Clinical Notes -->
                <div class="data-card">
                    <h3><i class="fas fa-notes-medical"></i> Recent Clinical Notes</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Date</th>
                                <th>Preview</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recent_notes) > 0): ?>
                                <?php foreach ($recent_notes as $note): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($note['patient_name']); ?></strong>
                                            <br><small><?php echo htmlspecialchars($note['patient_id']); ?></small>
                                        </td>
                                        <td><?php echo date('M j', strtotime($note['created_at'])); ?></td>
                                        <td>
                                            <?php if (!empty($note['diagnosis'])): ?>
                                                <strong>Dx:</strong> <?php echo strlen($note['diagnosis']) > 20 ? substr($note['diagnosis'], 0, 20) . '...' : $note['diagnosis']; ?>
                                            <?php elseif (!empty($note['subjective_preview'])): ?>
                                                <?php echo htmlspecialchars($note['subjective_preview']); ?>...
                                            <?php else: ?>
                                                <em>No content</em>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: #7f8c8d;">No recent clinical notes</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div style="text-align: right; margin-top: 10px;">
                        <a href="clinical_notes.php" class="action-link">View All Notes →</a>
                    </div>
                </div>

               
            </div>
        </div>
    </div>

    <!-- Refresh Button -->
    <button class="refresh-btn" onclick="location.reload();" title="Refresh Dashboard">
        <i class="fas fa-sync-alt"></i>
    </button>

    <script>
        // Appointment Types Pie Chart
        const appointmentCtx = document.getElementById('appointmentChart')?.getContext('2d');
        if (appointmentCtx) {
            const appointmentChart = new Chart(appointmentCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($appointment_stats, 'appointment_type')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($appointment_stats, 'count')); ?>,
                        backgroundColor: [
                            '#667eea', '#764ba2', '#f093fb', '#f5576c',
                            '#4facfe', '#00f2fe', '#43e97b', '#38f9d7'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                font: {
                                    size: 11
                                },
                                padding: 15
                            }
                        }
                    }
                }
            });
        }

        // Auto-refresh every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>