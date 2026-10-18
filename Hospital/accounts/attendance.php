<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

// Get current date or selected date
$selected_date = $_GET['date'] ?? date('Y-m-d');
$view = $_GET['view'] ?? 'attendance'; // attendance, timesheets, leave
$week_start = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'check_in') {
            $user_id = $_POST['user_id'];
            $check_in_time = date('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("
                INSERT INTO attendance (user_id, check_in, check_in_date, status) 
                VALUES (?, ?, DATE(?), 'present')
                ON DUPLICATE KEY UPDATE 
                check_in = VALUES(check_in), status = 'present'
            ");
            $stmt->execute([$user_id, $check_in_time, $selected_date]);
            
        } elseif ($_POST['action'] === 'check_out') {
            $user_id = $_POST['user_id'];
            $check_out_time = date('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET check_out = ?, working_hours = TIMESTAMPDIFF(MINUTE, check_in, ?)/60.0
                WHERE user_id = ? AND DATE(check_in_date) = DATE(?)
            ");
            $stmt->execute([$check_out_time, $check_out_time, $user_id, $selected_date]);
            
        } elseif ($_POST['action'] === 'submit_leave') {
            $staff_id = $_POST['staff_id'];
            $leave_type = $_POST['leave_type'];
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $reason = $_POST['reason'];
            
            $stmt = $pdo->prepare("
                INSERT INTO leave_requests 
                (user_id, leave_type, start_date, end_date, reason, status) 
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$staff_id, $leave_type, $start_date, $end_date, $reason]);
        }
        
        header("Location: attendance.php?view=$view&date=$selected_date");
        exit;
    }
}

// Get staff members
$staff_stmt = $pdo->query("
    SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name, 
           u.role, d.name as department, u.profile_picture
    FROM users u 
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.role IN ('Doctor', 'Nurse', 'Lab Technician', 'Receptionist')
    AND u.status = 'active'
    ORDER BY u.department_id, u.first_name
");
$staff = $staff_stmt->fetchAll();

// Get today's attendance
$attendance_stmt = $pdo->prepare("
    SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as staff_name,
           u.role, d.name as department
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE DATE(a.check_in_date) = ?
    ORDER BY a.check_in
");
$attendance_stmt->execute([$selected_date]);
$today_attendance = $attendance_stmt->fetchAll();

// Get leave requests for today
$leave_stmt = $pdo->prepare("
    SELECT lr.*, CONCAT(u.first_name, ' ', u.last_name) as staff_name,
           u.role, d.name as department
    FROM leave_requests lr
    JOIN users u ON lr.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE ? BETWEEN lr.start_date AND lr.end_date
    AND lr.status = 'approved'
");
$leave_stmt->execute([$selected_date]);
$on_leave = $leave_stmt->fetchAll();

// Get late arrivals (more than 30 minutes late)
$late_stmt = $pdo->prepare("
    SELECT COUNT(*) as late_count
    FROM attendance 
    WHERE DATE(check_in_date) = ? 
    AND TIME(check_in) > '09:00:00'
    AND TIMESTAMPDIFF(MINUTE, '09:00:00', TIME(check_in)) > 30
");
$late_stmt->execute([$selected_date]);
$late_arrivals = $late_stmt->fetch()['late_count'];

// Calculate statistics
$total_staff = count($staff);
$present_today = count($today_attendance);
$on_leave_count = count($on_leave);
$attendance_rate = $total_staff > 0 ? round(($present_today / $total_staff) * 100) : 0;

// Get weekly timesheets
$week_end = date('Y-m-d', strtotime('sunday this week', strtotime($week_start)));
$timesheets_stmt = $pdo->prepare("
    SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as staff_name,
           u.role, d.name as department,
           COALESCE(SUM(a.working_hours), 0) as total_hours,
           COALESCE(SUM(CASE WHEN a.working_hours > 8 THEN a.working_hours - 8 ELSE 0 END), 0) as overtime
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN attendance a ON u.id = a.user_id 
        AND DATE(a.check_in_date) BETWEEN ? AND ?
    WHERE u.role IN ('Doctor', 'Nurse', 'Lab Technician', 'Receptionist')
    AND u.status = 'active'
    GROUP BY u.id
    ORDER BY d.name, u.first_name
");
$timesheets_stmt->execute([$week_start, $week_end]);
$timesheets = $timesheets_stmt->fetchAll();

// Get all leave requests
$all_leave_stmt = $pdo->prepare("
    SELECT lr.*, CONCAT(u.first_name, ' ', u.last_name) as staff_name,
           u.role, d.name as department,
           DATEDIFF(lr.end_date, lr.start_date) + 1 as days_count
    FROM leave_requests lr
    JOIN users u ON lr.user_id = u.id
    LEFT JOIN departments d ON u.department_id = d.id
    ORDER BY lr.created_at DESC
");
$all_leave_stmt->execute();
$all_leave_requests = $all_leave_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Attendance - MediCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
            min-height: 100vh;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.875rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #64748b;
            font-size: 0.875rem;
        }

        /* Tabs Navigation */
        .attendance-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            background: white;
            padding: 0.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .tab-btn {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            background: transparent;
            color: #64748b;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .tab-btn:hover {
            background: #f8fafc;
            color: #374151;
        }

        .tab-btn.active {
            background: #3b82f6;
            color: white;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-main {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stat-icon.present {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .stat-icon.leave {
            background: #fef3c7;
            color: #d97706;
        }

        .stat-icon.late {
            background: #fee2e2;
            color: #dc2626;
        }

        .stat-title {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0.25rem;
        }

        .stat-subtitle {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .trend-up {
            color: #10b981;
        }

        .trend-down {
            color: #ef4444;
        }

        /* Content Cards */
        .content-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
        }

        .card-actions {
            display: flex;
            gap: 0.75rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background: white;
            color: #374151;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .card-content {
            padding: 1.5rem;
        }

        /* Tables */
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-table th {
            background: #f8fafc;
            padding: 0.75rem 1rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
        }

        .attendance-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.875rem;
        }

        .attendance-table tr:last-child td {
            border-bottom: none;
        }

        .staff-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .staff-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .staff-details h4 {
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.125rem;
        }

        .staff-details p {
            font-size: 0.75rem;
            color: #64748b;
        }

        .time-slot {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
        }

        .time-circle {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }

        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-present {
            background: #d1fae5;
            color: #065f46;
        }

        .status-absent {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-late {
            background: #fef3c7;
            color: #92400e;
        }

        .status-pending {
            background: #e0e7ff;
            color: #3730a3;
        }

        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Timesheet Cards */
        .timesheet-cards {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .timesheet-card {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1.5rem;
            transition: all 0.2s;
        }

        .timesheet-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .timesheet-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .timesheet-staff {
            flex: 1;
        }

        .staff-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .staff-department {
            color: #64748b;
            font-size: 0.875rem;
        }

        .timesheet-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .timesheet-stat {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 500;
        }

        .stat-value {
            font-size: 0.875rem;
            color: #0f172a;
            font-weight: 600;
        }

        .week-days {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .day-status {
            width: 24px;
            height: 24px;
            border-radius: 0.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }

        .day-present {
            background: #d1fae5;
            color: #065f46;
        }

        .day-absent {
            background: #fee2e2;
            color: #dc2626;
        }

        .day-leave {
            background: #fef3c7;
            color: #d97706;
        }

        /* Leave Request Form */
        .leave-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
        }

        .form-control {
            padding: 0.625rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        /* Leave Request Cards */
        .leave-cards {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .leave-card {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1.5rem;
            transition: all 0.2s;
        }

        .leave-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .leave-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .leave-staff {
            flex: 1;
        }

        .leave-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 500;
        }

        .detail-value {
            font-size: 0.875rem;
            color: #0f172a;
            font-weight: 500;
        }

        /* Date Navigation */
        .date-navigation {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .nav-btn {
            width: 32px;
            height: 32px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 0.375rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
        }

        .nav-btn:hover {
            background: #f8fafc;
        }

        .current-date {
            font-size: 0.875rem;
            font-weight: 500;
            color: #0f172a;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }

        .empty-state h3 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #64748b;
        }

        .empty-state p {
            font-size: 0.875rem;
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .leave-form {
                grid-template-columns: 1fr;
            }

            .timesheet-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="page-header">
        <?php if ($view === 'attendance'): ?>
            <h1>Present Today</h1>
        <?php elseif ($view === 'timesheets'): ?>
            <h1>Staff Timesheets</h1>
        <?php elseif ($view === 'leave'): ?>
            <h1>Leave Requests</h1>
        <?php endif; ?>
        <p>
            <?php if ($view === 'attendance'): ?>
                View and manage daily staff attendance
            <?php elseif ($view === 'timesheets'): ?>
                View and manage staff working hours
            <?php elseif ($view === 'leave'): ?>
                Manage staff leave and time off requests
            <?php endif; ?>
        </p>
    </div>

    <div class="attendance-tabs">
        <button class="tab-btn <?php echo $view === 'attendance' ? 'active' : ''; ?>" onclick="changeView('attendance')">
            <i class="fas fa-calendar-day"></i>
            Daily Attendance
        </button>
        <button class="tab-btn <?php echo $view === 'timesheets' ? 'active' : ''; ?>" onclick="changeView('timesheets')">
            <i class="fas fa-clock"></i>
            Timesheets
        </button>
        <button class="tab-btn <?php echo $view === 'leave' ? 'active' : ''; ?>" onclick="changeView('leave')">
            <i class="fas fa-calendar-alt"></i>
            Leave Requests
        </button>
    </div>

    <?php if ($view === 'attendance'): ?>
        <!-- Daily Attendance View -->
        <div class="date-navigation">
            <button class="nav-btn" onclick="navigateDate(-1)"><i class="fas fa-chevron-left"></i></button>
            <span class="current-date"><?php echo date('F j, Y', strtotime($selected_date)); ?></span>
            <button class="nav-btn" onclick="navigateDate(1)"><i class="fas fa-chevron-right"></i></button>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-main">
                    <div>
                        <div class="stat-number"><?php echo $present_today; ?></div>
                        <div class="stat-title">Out of <?php echo $total_staff; ?> staff members</div>
                    </div>
                    <div class="stat-icon present">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    +2% All Departments
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-main">
                    <div>
                        <div class="stat-number"><?php echo $on_leave_count; ?></div>
                        <div class="stat-title">Approved leave requests</div>
                    </div>
                    <div class="stat-icon leave">
                        <i class="fas fa-umbrella-beach"></i>
                    </div>
                </div>
                <div class="stat-trend trend-up">
                    <i class="fas fa-arrow-up"></i>
                    +1% Unplanned absences
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-main">
                    <div>
                        <div class="stat-number"><?php echo $late_arrivals; ?></div>
                        <div class="stat-title">More than 30 minutes late</div>
                    </div>
                    <div class="stat-icon late">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-trend trend-down">
                    <i class="fas fa-arrow-down"></i>
                    -3% Export
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h2>Daily Attendance Record</h2>
                <div class="card-actions">
                    <button class="btn">
                        <i class="fas fa-download"></i>
                        Export
                    </button>
                </div>
            </div>
            <div class="card-content">
                <?php if (empty($today_attendance)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No Attendance Records</h3>
                        <p>No staff have checked in for today yet.</p>
                    </div>
                <?php else: ?>
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Staff</th>
                                <th>Department</th>
                                <th>Role</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Hours</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($today_attendance as $record): 
                                $check_out = $record['check_out'] ? date('h:i A', strtotime($record['check_out'])) : '--:--';
                                $hours = $record['working_hours'] ? number_format($record['working_hours'], 1) : '--';
                            ?>
                                <tr>
                                    <td>
                                        <div class="staff-info">
                                            <div class="staff-avatar">
                                                <?php echo substr($record['staff_name'], 0, 1); ?>
                                            </div>
                                            <div class="staff-details">
                                                <h4><?php echo htmlspecialchars($record['staff_name']); ?></h4>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['department']); ?></td>
                                    <td><?php echo htmlspecialchars($record['role']); ?></td>
                                    <td>
                                        <div class="time-slot">
                                            <div class="time-circle">
                                                <i class="fas fa-sign-in-alt"></i>
                                            </div>
                                            <?php echo date('h:i A', strtotime($record['check_in'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="time-slot">
                                            <div class="time-circle">
                                                <i class="fas fa-sign-out-alt"></i>
                                            </div>
                                            <?php echo $check_out; ?>
                                        </div>
                                    </td>
                                    <td><?php echo $hours; ?></td>
                                    <td>
                                        <span class="status-badge status-present">Present</span>
                                    </td>
                                    <td>
                                        <button class="btn">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($view === 'timesheets'): ?>
        <!-- Timesheets View -->
        <div class="date-navigation">
            <button class="nav-btn" onclick="navigateWeek(-1)"><i class="fas fa-chevron-left"></i></button>
            <span class="current-date">
                Week of <?php echo date('F j, Y', strtotime($week_start)); ?>
            </span>
            <button class="nav-btn" onclick="navigateWeek(1)"><i class="fas fa-chevron-right"></i></button>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h2>This Week</h2>
                <div class="card-actions">
                    <button class="btn">
                        <i class="fas fa-download"></i>
                        Export
                    </button>
                    <button class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Add Timesheet
                    </button>
                </div>
            </div>
            <div class="card-content">
                <?php if (empty($timesheets)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clock"></i>
                        <h3>No Timesheets</h3>
                        <p>No timesheet data available for this week.</p>
                    </div>
                <?php else: ?>
                    <div class="timesheet-cards">
                        <?php foreach ($timesheets as $timesheet): ?>
                            <div class="timesheet-card">
                                <div class="timesheet-header">
                                    <div class="timesheet-staff">
                                        <div class="staff-name"><?php echo htmlspecialchars($timesheet['staff_name']); ?></div>
                                        <div class="staff-department"><?php echo htmlspecialchars($timesheet['department']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="timesheet-stats">
                                    <div class="timesheet-stat">
                                        <span class="stat-label">Week Starting</span>
                                        <span class="stat-value"><?php echo date('M j, Y', strtotime($week_start)); ?></span>
                                    </div>
                                    <div class="timesheet-stat">
                                        <span class="stat-label">Total Hours</span>
                                        <span class="stat-value"><?php echo number_format($timesheet['total_hours'], 1); ?></span>
                                    </div>
                                    <div class="timesheet-stat">
                                        <span class="stat-label">Overtime</span>
                                        <span class="stat-value"><?php echo number_format($timesheet['overtime'], 1); ?></span>
                                    </div>
                                    <div class="timesheet-stat">
                                        <span class="stat-label">Status</span>
                                        <span class="status-badge status-present">Approved</span>
                                    </div>
                                </div>

                                <div class="week-days">
                                    <?php for ($i = 0; $i < 7; $i++): ?>
                                        <div class="day-status day-present">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($view === 'leave'): ?>
        <!-- Leave Requests View -->
        <div class="content-card">
            <div class="card-header">
                <h2>Submit Leave Request</h2>
                <p>Request time off or leave of absence</p>
            </div>
            <div class="card-content">
                <form class="leave-form" method="POST">
                    <input type="hidden" name="action" value="submit_leave">
                    
                    <div class="form-group">
                        <label class="form-label">Staff Member</label>
                        <select class="form-control" name="staff_id" required>
                            <option value="">Select staff member</option>
                            <?php foreach ($staff as $staff_member): ?>
                                <option value="<?php echo $staff_member['id']; ?>">
                                    <?php echo htmlspecialchars($staff_member['name']); ?> - <?php echo htmlspecialchars($staff_member['role']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Leave Type</label>
                        <select class="form-control" name="leave_type" required>
                            <option value="">Select leave type</option>
                            <option value="vacation">Vacation</option>
                            <option value="sick">Sick Leave</option>
                            <option value="personal">Personal</option>
                            <option value="emergency">Emergency</option>
                            <option value="maternity">Maternity</option>
                            <option value="paternity">Paternity</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" required>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Reason (Optional)</label>
                        <textarea class="form-control" name="reason" placeholder="Brief explanation for the leave request"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h2>New Request</h2>
            </div>
            <div class="card-content">
                <?php if (empty($all_leave_requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Leave Requests</h3>
                        <p>No leave requests have been submitted yet.</p>
                    </div>
                <?php else: ?>
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Staff</th>
                                <th>Department</th>
                                <th>Leave Type</th>
                                <th>Duration</th>
                                <th>Date Range</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_leave_requests as $request): ?>
                                <tr>
                                    <td>
                                        <div class="staff-info">
                                            <div class="staff-avatar">
                                                <?php echo substr($request['staff_name'], 0, 1); ?>
                                            </div>
                                            <div class="staff-details">
                                                <h4><?php echo htmlspecialchars($request['staff_name']); ?></h4>
                                                <p><?php echo htmlspecialchars($request['role']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['department']); ?></td>
                                    <td><?php echo ucfirst($request['leave_type']); ?></td>
                                    <td><?php echo $request['days_count']; ?> days</td>
                                    <td>
                                        <?php echo date('Y-m-d', strtotime($request['start_date'])); ?> to 
                                        <?php echo date('Y-m-d', strtotime($request['end_date'])); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $request['status']; ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    function changeView(view) {
        const params = new URLSearchParams(window.location.search);
        params.set('view', view);
        window.location.href = `attendance.php?${params.toString()}`;
    }

    function navigateDate(direction) {
        const currentDate = new Date('<?php echo $selected_date; ?>');
        currentDate.setDate(currentDate.getDate() + direction);
        
        const dateString = currentDate.toISOString().split('T')[0];
        const params = new URLSearchParams(window.location.search);
        params.set('date', dateString);
        window.location.href = `attendance.php?${params.toString()}`;
    }

    function navigateWeek(direction) {
        const currentWeek = '<?php echo $week_start; ?>';
        const newDate = new Date(currentWeek);
        newDate.setDate(newDate.getDate() + (direction * 7));
        
        const dateString = newDate.toISOString().split('T')[0];
        const params = new URLSearchParams(window.location.search);
        params.set('week_start', dateString);
        window.location.href = `attendance.php?${params.toString()}`;
    }

    // Auto-submit forms when dates change in leave form
    document.addEventListener('DOMContentLoaded', function() {
        const startDateInput = document.querySelector('input[name="start_date"]');
        const endDateInput = document.querySelector('input[name="end_date"]');
        
        if (startDateInput) {
            startDateInput.value = '<?php echo date('Y-m-d'); ?>';
        }
        if (endDateInput) {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            endDateInput.value = tomorrow.toISOString().split('T')[0];
        }
    });
</script>

</body>
</html>