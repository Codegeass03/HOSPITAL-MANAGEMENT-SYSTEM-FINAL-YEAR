<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
include "admin/connection.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

date_default_timezone_set('Africa/Nairobi');

// Get the logged-in receptionist's ID
$receptionist_id = $_SESSION['user_id'] ?? null;

if (!$receptionist_id) {
    die("Receptionist not logged in.");
}

// Fetch Receptionist Information
try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.department
        FROM users u
        WHERE u.id = ? AND u.role = 'Receptionist'
    ");
    $stmt->execute([$receptionist_id]);
    $receptionist = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$receptionist) {
        die("Receptionist not found.");
    }
} catch (PDOException $e) {
    die("Error fetching receptionist information: " . $e->getMessage());
}

// Fetch Dashboard Statistics for Receptionists
$stats = [];

// 1. Today's Appointments
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE DATE(appointment_date) = CURDATE() 
        AND status IN ('scheduled', 'confirmed', 'in_progress')
    ");
    $stats['today_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['today_appointments'] = 0;
}

// 2. Pending Appointment Requests
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM appointment_requests 
        WHERE status = 'pending'
    ");
    $stats['pending_requests'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['pending_requests'] = 0;
}

// 3. New Patients (Last 7 days)
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM patients 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stats['new_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['new_patients'] = 0;
}

// 4. Available Doctors Today
try {
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT u.id) as count 
        FROM users u
        WHERE u.role = 'Doctor' 
        AND u.status = 'active'
        AND u.id IN (
            SELECT DISTINCT doctor_id 
            FROM appointments 
            WHERE DATE(appointment_date) = CURDATE()
        )
    ");
    $stats['available_doctors'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['available_doctors'] = 0;
}

// 5. Walk-in Patients Today
try {
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT a.patient_id) as count 
        FROM appointments a
        WHERE DATE(a.appointment_date) = CURDATE() 
        AND a.created_by IS NOT NULL
        AND a.created_by IN (SELECT id FROM users WHERE role = 'Receptionist')
    ");
    $stats['walkin_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['walkin_patients'] = 0;
}

// 6. Cancelled Appointments Today
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE DATE(appointment_date) = CURDATE() 
        AND status = 'cancelled'
    ");
    $stats['cancelled_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    $stats['cancelled_today'] = 0;
}

// Recent Appointments (Today)
$recent_appointments = [];
try {
    $stmt = $pdo->query("
        SELECT 
            a.id,
            a.appointment_number,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id,
            CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
            a.appointment_date,
            a.appointment_time,
            a.status,
            a.appointment_type,
            a.reason
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.doctor_id = u.id
        WHERE DATE(a.appointment_date) = CURDATE()
        ORDER BY a.appointment_time ASC
        LIMIT 8
    ");
    $recent_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_appointments = [];
}

// Pending Appointment Requests
$pending_requests = [];
try {
    $stmt = $pdo->query("
        SELECT 
            ar.id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.patient_id,
            p.phone,
            ar.preferred_date,
            ar.preferred_time,
            ar.type,
            ar.urgency,
            ar.notes,
            ar.created_at
        FROM appointment_requests ar
        JOIN patients p ON ar.patient_id = p.id
        WHERE ar.status = 'pending'
        ORDER BY 
            CASE ar.urgency 
                WHEN 'High' THEN 1
                WHEN 'Medium' THEN 2
                ELSE 3 
            END,
            ar.preferred_date ASC
        LIMIT 6
    ");
    $pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $pending_requests = [];
}

// Doctor Availability Today
$doctor_availability = [];
try {
    $stmt = $pdo->query("
        SELECT 
            u.id,
            CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
            u.department,
            u.specialization,
            COUNT(a.id) as appointment_count,
            (
                SELECT COUNT(*) 
                FROM appointments a2 
                WHERE a2.doctor_id = u.id 
                AND DATE(a2.appointment_date) = CURDATE() 
                AND a2.status IN ('scheduled', 'confirmed', 'in_progress')
            ) as today_appointments
        FROM users u
        LEFT JOIN appointments a ON u.id = a.doctor_id AND DATE(a.appointment_date) = CURDATE()
        WHERE u.role = 'Doctor' 
        AND u.status = 'active'
        GROUP BY u.id, u.first_name, u.last_name, u.department, u.specialization
        ORDER BY u.department, today_appointments ASC
        LIMIT 8
    ");
    $doctor_availability = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $doctor_availability = [];
}

// New Patients (Last 7 days)
$new_patients = [];
try {
    $stmt = $pdo->query("
        SELECT 
            p.id,
            p.patient_id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            p.phone,
            p.gender,
            p.age,
            p.created_at
        FROM patients p
        WHERE p.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY p.created_at DESC
        LIMIT 6
    ");
    $new_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $new_patients = [];
}

// Appointment Statistics by Status (Today)
$appointment_status_stats = [];
try {
    $stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM appointments 
        WHERE DATE(appointment_date) = CURDATE()
        GROUP BY status
        ORDER BY count DESC
    ");
    $appointment_status_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $appointment_status_stats = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionist Dashboard</title>
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

        .receptionist-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .receptionist-avatar {
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

        .receptionist-details h1 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .receptionist-details p {
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

        .status-scheduled { background: #e3f2fd; color: #1976d2; }
        .status-confirmed { background: #e8f5e9; color: #388e3c; }
        .status-completed { background: #f3e5f5; color: #7b1fa2; }
        .status-cancelled { background: #ffebee; color: #c62828; }
        .status-pending { background: #fff3e0; color: #ef6c00; }
        .status-in_progress { background: #e1f5fe; color: #0288d1; }

        .urgency-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            display: inline-block;
        }

        .urgency-high { background: #ffcdd2; color: #c62828; }
        .urgency-medium { background: #ffe0b2; color: #ef6c00; }
        .urgency-normal { background: #c8e6c9; color: #388e3c; }

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

        .doctor-availability {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .availability-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .availability-available { background: #4CAF50; }
        .availability-busy { background: #ff9800; }
        .availability-full { background: #f44336; }

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
            <div class="receptionist-info">
                <div class="receptionist-avatar">
                    <?php 
                        $initials = substr($receptionist['first_name'], 0, 1) . substr($receptionist['last_name'], 0, 1);
                        echo strtoupper($initials);
                    ?>
                </div>
                <div class="receptionist-details">
                    <h1><?php echo htmlspecialchars($receptionist['first_name'] . ' ' . $receptionist['last_name']); ?></h1>
                    <p>Receptionist • <?php echo htmlspecialchars($receptionist['department']); ?></p>
                </div>
            </div>
            <p>Last updated: <?php echo date('F j, Y g:i A'); ?></p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='appointments.php'">
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

            <div class="stat-card" onclick="window.location.href='appointment_requests.php'">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['pending_requests']; ?></div>
                        <div class="stat-label">Pending Requests</div>
                    </div>
                    <div class="stat-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card" onclick="window.location.href='patients.php'">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['new_patients']; ?></div>
                        <div class="stat-label">New Patients (7 days)</div>
                    </div>
                    <div class="stat-icon green">
                        <i class="fas fa-user-plus"></i>
                    </div>
                </div>
            </div>

            <!-- <div class="stat-card" onclick="window.location.href='doctors.php'">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['available_doctors']; ?></div>
                        <div class="stat-label">Available Doctors</div>
                    </div>
                    <div class="stat-icon teal">
                        <i class="fas fa-user-md"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card" onclick="window.location.href='reports.php'">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['walkin_patients']; ?></div>
                        <div class="stat-label">Walk-in Patients</div>
                    </div>
                    <div class="stat-icon purple">
                        <i class="fas fa-walking"></i>
                    </div>
                </div>
            </div> -->

            <div class="stat-card" onclick="window.location.href='appointments.php?filter=cancelled'">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo $stats['cancelled_today']; ?></div>
                        <div class="stat-label">Cancelled Today</div>
                    </div>
                    <div class="stat-icon red">
                        <i class="fas fa-times-circle"></i>
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
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recent_appointments) > 0): ?>
                                <?php foreach ($recent_appointments as $apt): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></strong>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($apt['patient_name']); ?></strong>
                                            <br><small><?php echo htmlspecialchars($apt['patient_id']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                                        <td><?php echo ucfirst($apt['appointment_type']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $apt['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $apt['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #7f8c8d;">No appointments today</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div style="text-align: right; margin-top: 10px;">
                        <a href="appointments.php" class="action-link">View All Appointments →</a>
                    </div>
                </div>

                <!-- Pending Appointment Requests -->
                <div class="data-card">
                    <h3><i class="fas fa-clock"></i> Pending Appointment Requests</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Preferred Date</th>
                                <th>Time</th>
                                <th>Urgency</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($pending_requests) > 0): ?>
                                <?php foreach ($pending_requests as $request): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($request['patient_name']); ?></strong>
                                            <br><small><?php echo htmlspecialchars($request['patient_id']); ?></small>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($request['preferred_date'])); ?></td>
                                        <td><?php echo ucfirst($request['preferred_time']); ?></td>
                                        <td>
                                            <span class="urgency-badge urgency-<?php echo strtolower($request['urgency']); ?>">
                                                <?php echo $request['urgency']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="appointment_requests.php?action=review&id=<?php echo $request['id']; ?>" class="action-link">Review</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #7f8c8d;">No pending requests</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div style="text-align: right; margin-top: 10px;">
                        <a href="appointment_requests.php" class="action-link">Manage All Requests →</a>
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
                        <a href="add_new_patients.php" class="quick-action-btn">
                            <i class="fas fa-user-plus"></i>
                            <div>Register Patient</div>
                        </a>
                        <a href="doctors_schedule.php" class="quick-action-btn">
                            <i class="fas fa-calendar-alt"></i>
                            <div>View Schedules</div>
                        </a>
                        <a href="appointment_requests.php" class="quick-action-btn">
                            <i class="fas fa-tasks"></i>
                            <div>Manage Requests</div>
                        </a>
                    </div>
                </div>

                <!-- Doctor Availability -->
                <div class="data-card">
                    <h3><i class="fas fa-user-md"></i> Doctor Availability Today</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Doctor</th>
                                <th>Department</th>
                                <th>Appointments</th>
                                <th>Availability</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($doctor_availability) > 0): ?>
                                <?php foreach ($doctor_availability as $doctor): ?>
                                    <tr>
                                        <td>
                                            <strong>Dr. <?php echo htmlspecialchars($doctor['doctor_name']); ?></strong>
                                            <?php if (!empty($doctor['specialization'])): ?>
                                                <br><small><?php echo htmlspecialchars($doctor['specialization']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($doctor['department']); ?></td>
                                        <td><?php echo $doctor['today_appointments']; ?> today</td>
                                        <td>
                                            <div class="doctor-availability">
                                                <?php
                                                $availability = 'available';
                                                $availability_text = 'Available';
                                                if ($doctor['today_appointments'] >= 10) {
                                                    $availability = 'full';
                                                    $availability_text = 'Full';
                                                } elseif ($doctor['today_appointments'] >= 5) {
                                                    $availability = 'busy';
                                                    $availability_text = 'Busy';
                                                }
                                                ?>
                                                <span class="availability-dot availability-<?php echo $availability; ?>"></span>
                                                <span><?php echo $availability_text; ?></span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #7f8c8d;">No doctor data available</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div style="text-align: right; margin-top: 10px;">
                        <a href="doctor_schedules.php" class="action-link">View All Schedules →</a>
                    </div>
                </div>

                <!-- New Patients -->
                <div class="data-card">
                    <h3><i class="fas fa-user-plus"></i> New Patients (Last 7 Days)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Contact</th>
                                <th>Age/Gender</th>
                                <th>Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($new_patients) > 0): ?>
                                <?php foreach ($new_patients as $patient): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($patient['patient_name']); ?></strong>
                                            <br><small><?php echo htmlspecialchars($patient['patient_id']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($patient['phone']); ?></td>
                                        <td><?php echo $patient['age']; ?> / <?php echo $patient['gender']; ?></td>
                                        <td><?php echo date('M j', strtotime($patient['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #7f8c8d;">No new patients</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div style="text-align: right; margin-top: 10px;">
                        <a href="add_new_patients.php" class="action-link">View All Patients →</a>
                    </div>
                </div>

                <!-- Appointment Status Chart -->
                <!-- <div class="data-card">
                    <h3><i class="fas fa-chart-pie"></i> Today's Appointments Status</h3>
                    <div class="chart-container">
                        <?php if (!empty($appointment_status_stats)): ?>
                            <canvas id="appointmentStatusChart"></canvas>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-chart-pie fa-2x" style="margin-bottom: 10px;"></i>
                                <p>No appointment data</p>
                            </div>
                        <?php endif; ?>
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
        // Appointment Status Pie Chart
        const appointmentStatusCtx = document.getElementById('appointmentStatusChart')?.getContext('2d');
        if (appointmentStatusCtx) {
            const appointmentStatusChart = new Chart(appointmentStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($appointment_status_stats, 'status')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($appointment_status_stats, 'count')); ?>,
                        backgroundColor: [
                            '#1976d2', '#388e3c', '#7b1fa2', '#c62828',
                            '#0288d1', '#ef6c00', '#455a64'
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