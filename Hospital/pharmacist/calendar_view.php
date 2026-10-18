<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

// Get current date or selected date
$selected_date = $_GET['date'] ?? date('Y-m-d');
$view = $_GET['view'] ?? 'day'; // Default to 'day' view as shown in the first screenshot
$doctor_filter = $_GET['doctor'] ?? 'all';

// Get all doctors for filter
$doctors_stmt = $pdo->query("
    SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name 
    FROM users u 
    WHERE u.role = 'Doctor' AND u.status = 'active'
    ORDER BY u.first_name
");
$doctors = $doctors_stmt->fetchAll();

// Get doctor names for quick reference
$doctor_names = [];
foreach ($doctors as $doc) {
    $doctor_names[$doc['id']] = $doc['name'];
}

// Get appointments based on view and filters
function getAppointments($pdo, $date, $view, $doctor_filter) {
    $where_doctor = $doctor_filter !== 'all' ? "AND a.doctor_id = :doctor_id" : "";
    
    // Determine start and end dates based on view
    $start_date = $date;
    $end_date = $date;
    $date_clause = "a.appointment_date = :start";

    if ($view === 'week') {
        // Adjust $date to be within the desired week
        $start_date = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        $end_date = date('Y-m-d', strtotime('sunday this week', strtotime($date)));
        $date_clause = "a.appointment_date BETWEEN :start AND :end";
    } elseif ($view === 'month') {
        $start_date = date('Y-m-01', strtotime($date));
        $end_date = date('Y-m-t', strtotime($date));
        $date_clause = "a.appointment_date BETWEEN :start AND :end";
    }

    $sql = "
        SELECT a.*, 
               CONCAT(p.first_name, ' ', p.last_name) as patient_name,
               CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
               p.phone as patient_phone
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users d ON a.doctor_id = d.id
        WHERE $date_clause $where_doctor
        ORDER BY a.appointment_date, a.appointment_time
    ";
    
    $params = [':start' => $start_date];
    if ($view === 'week' || $view === 'month') {
        $params[':end'] = $end_date;
    }
    if ($doctor_filter !== 'all') {
        $params[':doctor_id'] = $doctor_filter;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

$appointments = getAppointments($pdo, $selected_date, $view, $doctor_filter);

// Organize appointments by date for month and week views
$appointments_by_date = [];
foreach ($appointments as $apt) {
    $appointments_by_date[$apt['appointment_date']][] = $apt;
}

// For week view, organize by day of week
$week_days = [];
if ($view === 'week') {
    $start_date = date('Y-m-d', strtotime('monday this week', strtotime($selected_date)));
    for ($i = 0; $i < 7; $i++) {
        $day = date('Y-m-d', strtotime("+$i days", strtotime($start_date)));
        $week_days[] = [
            'date' => $day,
            'day_name' => date('D', strtotime($day)),
            'day_number' => date('j', strtotime($day)),
            'appointments' => $appointments_by_date[$day] ?? []
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Calendar - MediCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- General CSS --- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
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
            margin-bottom: 0.25rem;
        }

        .page-header p {
            color: #64748b;
            font-size: 0.875rem;
        }

        /* Layout */
        .schedule-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 1.5rem;
        }

        /* Sidebar */
        .schedule-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .sidebar-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .sidebar-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 1rem;
        }

        .sidebar-card p {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 1rem;
        }

        /* Calendar Widget */
        .calendar-widget {
            width: 100%;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .calendar-header h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: #0f172a;
        }

        .calendar-nav {
            display: flex;
            gap: 0.25rem;
        }

        .calendar-nav button {
            width: 28px;
            height: 28px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 0.375rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
        }

        .calendar-nav button:hover {
            background: #f8fafc;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.25rem;
        }

        .calendar-day-header {
            text-align: center;
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 500;
            padding: 0.5rem 0;
        }

        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            color: #64748b;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .calendar-day:hover {
            background: #f1f5f9;
        }

        .calendar-day.today {
            background: #0f172a;
            color: white;
            font-weight: 600;
        }

        .calendar-day.selected {
            background: #3b82f6;
            color: white;
        }

        .calendar-day.other-month {
            color: #cbd5e1;
        }

        /* Filter Section */
        .filter-select {
            width: 100%;
            padding: 0.625rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }

        .add-appointment-btn {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        /* Main Schedule Area */
        .schedule-main {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .schedule-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .view-tabs {
            display: flex;
            gap: 0.5rem;
            background: #f1f5f9;
            padding: 0.25rem;
            border-radius: 0.5rem;
        }

        .view-tab {
            padding: 0.5rem 1rem;
            background: transparent;
            border: none;
            color: #64748b;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .view-tab.active {
            background: white;
            color: #0f172a;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .date-navigation {
            display: flex;
            align-items: center;
            gap: 1rem;
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

        /* Schedule Content */
        .schedule-content {
            padding: 1.5rem;
        }

        .schedule-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .schedule-subtitle {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 1.5rem;
        }

        /* Day View Styles */
        .time-slots {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .time-slot {
            display: flex;
            padding: 1rem;
            border: 1px solid #f1f5f9;
            border-radius: 0.5rem;
            min-height: 60px;
        }

        .time-label {
            width: 100px;
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 500;
        }

        .slot-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .appointment-card {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            border-left: 3px solid #3b82f6;
            background: #eff6ff;
            cursor: pointer;
            transition: all 0.2s;
        }

        .appointment-card:hover {
            background: #dbeafe;
            transform: translateX(2px);
        }

        .appointment-card.cancelled {
            border-left-color: #ef4444;
            background: #fef2f2;
        }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .patient-name {
            font-weight: 600;
            color: #0f172a;
            font-size: 0.875rem;
        }

        .appointment-time {
            font-size: 0.75rem;
            color: #64748b;
        }

        .appointment-details {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.75rem;
            color: #64748b;
        }

        .doctor-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .doctor-avatar {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #cbd5e1;
        }

        .status-badge {
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-in-progress {
            background: #fef3c7;
            color: #92400e;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-scheduled {
            background: #e0e7ff;
            color: #3730a3;
        }

        .no-appointments {
            text-align: center;
            color: #94a3b8;
            font-size: 0.875rem;
            padding: 2rem 0;
        }

        /* Week View Styles */
        .week-view-grid {
            display: grid;
            grid-template-columns: 100px repeat(7, 1fr);
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .week-time-header {
            background: #f8fafc;
            padding: 0.75rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #64748b;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }

        .week-day-header {
            background: #f8fafc;
            padding: 0.75rem;
            text-align: center;
            font-size: 0.875rem;
            font-weight: 500;
            color: #0f172a;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }

        .week-day-header:last-child {
            border-right: none;
        }

        .week-time-slot {
            padding: 0.75rem;
            font-size: 0.875rem;
            color: #64748b;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            min-height: 80px;
        }

        .week-time-slot:last-child {
            border-bottom: none;
        }

        .week-day-cell {
            min-height: 80px;
            padding: 0.5rem;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            position: relative;
        }

        .week-day-cell:last-child {
            border-right: none;
        }

        .week-appointment {
            font-size: 0.75rem;
            padding: 0.5rem;
            margin-bottom: 0.25rem;
            border-radius: 0.25rem;
            border-left: 3px solid #3b82f6;
            background: #eff6ff;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            cursor: pointer;
        }

        .week-appointment.cancelled {
            border-left-color: #ef4444;
            background: #fef2f2;
        }

        /* Month View Styles */
        .month-view-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .month-day-header {
            text-align: center;
            font-size: 0.875rem;
            font-weight: 500;
            color: #0f172a;
            padding: 0.75rem;
            background: #f8fafc;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }

        .month-day-header:last-child {
            border-right: none;
        }

        .month-day-cell {
            min-height: 120px;
            padding: 0.5rem;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            background: white;
            position: relative;
            cursor: pointer;
            transition: background-color 0.1s;
        }
        
        .month-day-cell:hover {
            background-color: #f8fafc;
        }

        .month-day-cell:last-child {
            border-right: none;
        }

        .month-day-number {
            font-size: 0.875rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
            text-align: right;
        }

        .month-day-cell.other-month {
            background-color: #f8fafc;
        }
        
        .month-day-cell.other-month .month-day-number {
            color: #94a3b8;
        }

        .month-appointment {
            font-size: 0.75rem;
            color: #0f172a;
            padding: 0.25rem 0.5rem;
            margin-bottom: 0.25rem;
            border-radius: 0.25rem;
            border-left: 3px solid #3b82f6;
            background: #eff6ff;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        
        .month-appointment.cancelled {
            border-left-color: #ef4444;
            background: #fef2f2;
        }

        /* List View Styles */
        .appointments-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .list-appointment-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }

        .list-appointment-card:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }

        .list-patient-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .color-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #fbbf24; 
        }
        
        .list-appointment-card.cancelled .color-indicator {
            background: #ef4444; 
        }

        .list-patient-details h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .list-time {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.75rem;
            color: #64748b;
        }
        
        .list-time i {
            color: #94a3b8;
        }

        .list-doctor-status {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .list-doctor {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .list-doctor-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 1rem;
            font-weight: bold;
        }

        .list-doctor-name {
            font-size: 0.875rem;
            color: #0f172a;
        }

        @media (max-width: 1024px) {
            .schedule-layout {
                grid-template-columns: 1fr;
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="page-header">
        <h1>Appointment Calendar</h1>
        <p>View and manage appointments in calendar view.</p>
    </div>

    <div class="schedule-layout">
        <div class="schedule-sidebar">
            <div class="sidebar-card">
                <h3>Calendar</h3>
                <p>Select a date to view schedules</p>

                <div class="calendar-widget">
                    <div class="calendar-header">
                        <h4><?php echo date('F Y', strtotime($selected_date)); ?></h4>
                        <div class="calendar-nav">
                            <button onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                            <button onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>

                    <div class="calendar-grid">
                        <div class="calendar-day-header">Su</div>
                        <div class="calendar-day-header">Mo</div>
                        <div class="calendar-day-header">Tu</div>
                        <div class="calendar-day-header">We</div>
                        <div class="calendar-day-header">Th</div>
                        <div class="calendar-day-header">Fr</div>
                        <div class="calendar-day-header">Sa</div>

                        <?php
                        // PHP logic to generate sidebar calendar days
                        $month_start = date('Y-m-01', strtotime($selected_date));
                        $first_day_of_month_w = date('w', strtotime($month_start)); // 0 (Sun) to 6 (Sat)
                        $days_in_month = date('t', strtotime($selected_date));
                        $today = date('Y-m-d');
                        
                        // Previous month days
                        $prev_month_days = date('t', strtotime('-1 month', strtotime($month_start)));
                        for ($i = $first_day_of_month_w - 1; $i >= 0; $i--) {
                            $day = $prev_month_days - $i;
                            echo '<div class="calendar-day other-month">' . $day . '</div>';
                        }
                        
                        // Current month days
                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $date = date('Y-m-d', strtotime("$month_start +".($day-1)." days"));
                            $is_today = $date === $today ? 'today' : '';
                            $is_selected = $date === $selected_date ? 'selected' : '';
                            echo '<div class="calendar-day ' . $is_today . ' ' . $is_selected . '" onclick="selectDate(\'' . $date . '\')">' . $day . '</div>';
                        }
                        
                        // Next month days
                        $remaining = 42 - ($first_day_of_month_w + $days_in_month);
                        if ($remaining < 7) $remaining += 7; // Ensure 6 weeks if necessary
                        $i = 1;
                        while (($first_day_of_month_w + $days_in_month + $i - 1) % 7 != 0 || ($first_day_of_month_w + $days_in_month + $i - 1) < 35 ) {
                               if ($i > 14) break; // Safety break
                               echo '<div class="calendar-day other-month">' . $i . '</div>';
                               $i++;
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div class="sidebar-card">
                <h3>Filter by Doctor</h3>
                <select class="filter-select" id="doctorFilter" onchange="filterByDoctor(this.value)">
                    <option value="all">All Doctors</option>
                    <?php foreach ($doctors as $doc): ?>
                        <option value="<?php echo $doc['id']; ?>" <?php echo $doctor_filter == $doc['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($doc['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button class="add-appointment-btn" onclick="window.location.href='appointment_scheduling.php'">
                <i class="fas fa-plus"></i>
                New Appointment
            </button>
        </div>

        <div class="schedule-main">
            <div class="schedule-header">
                <div class="view-tabs">
                    <button class="view-tab <?php echo $view === 'day' ? 'active' : ''; ?>" onclick="changeView('day')">Day</button>
                    <button class="view-tab <?php echo $view === 'week' ? 'active' : ''; ?>" onclick="changeView('week')">Week</button>
                    <button class="view-tab <?php echo $view === 'month' ? 'active' : ''; ?>" onclick="changeView('month')">Month</button>
                </div>

                <div class="date-navigation">
                    <button class="nav-btn" onclick="navigateDate(-1)"><i class="fas fa-chevron-left"></i></button>
                    <span class="current-date">
                        <?php 
                        if ($view === 'day') {
                            echo date('l, F j, Y', strtotime($selected_date));
                        } elseif ($view === 'week') {
                            $start_week = date('F j', strtotime('monday this week', strtotime($selected_date)));
                            $end_week = date('F j, Y', strtotime('sunday this week', strtotime($selected_date)));
                            echo $start_week . ' - ' . $end_week;
                        } elseif ($view === 'month') {
                            echo date('F Y', strtotime($selected_date));
                        }
                        ?>
                    </span>
                    <button class="nav-btn" onclick="navigateDate(1)"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>

            <div class="schedule-content">
                <?php if ($view === 'day'): ?>
                    <h2 class="schedule-title">Daily Schedule</h2>
                    <p class="schedule-subtitle">
                        Schedule for <?php echo date('F j, Y', strtotime($selected_date)); ?> • 
                        <?php echo $doctor_filter === 'all' ? 'All Doctors' : htmlspecialchars($doctor_names[$doctor_filter] ?? 'Filtered'); ?>
                    </p>

                    <div class="time-slots">
                        <?php
                        // Time slots for Day View
                        $time_slots = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
                        
                        foreach ($time_slots as $slot_start):
                            $hour = (int)date('H', strtotime($slot_start));
                            $slot_appointments = array_filter($appointments, function($apt) use ($hour) {
                                $apt_hour = (int)date('H', strtotime($apt['appointment_time']));
                                return $apt_hour === $hour;
                            });
                        ?>
                            <div class="time-slot">
                                <div class="time-label"><?php echo date('g:i A', strtotime($slot_start)); ?></div>
                                <div class="slot-content">
                                    <?php if (empty($slot_appointments)): ?>
                                        <div class="no-appointments">No appointments</div>
                                    <?php else: ?>
                                        <?php foreach ($slot_appointments as $apt): 
                                            $status_class = strtolower($apt['status']);
                                        ?>
                                            <div class="appointment-card <?php echo $status_class; ?>">
                                                <div class="appointment-header">
                                                    <span class="patient-name"><?php echo htmlspecialchars($apt['patient_name']); ?></span>
                                                    <span class="status-badge status-<?php echo $status_class; ?>">
                                                        <?php echo ucwords(str_replace('_', ' ', $status_class)); ?>
                                                    </span>
                                                </div>
                                                <div class="appointment-details">
                                                    <span class="appointment-time">
                                                        <?php echo date('g:i A', strtotime($apt['appointment_time'])); ?> - 
                                                        <?php echo date('g:i A', strtotime($apt['appointment_time'] . ' +' . $apt['duration'] . ' minutes')); ?>
                                                    </span>
                                                    <div class="doctor-info">
                                                        <div class="doctor-avatar"></div>
                                                        Dr. <?php echo htmlspecialchars(explode(' ', $apt['doctor_name'])[1] ?? $apt['doctor_name']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                
                <?php elseif ($view === 'week'): ?>
                    <h2 class="schedule-title">Weekly Schedule</h2>
                    <p class="schedule-subtitle">
                        Schedule for <?php 
                            $start_week = date('F j', strtotime('monday this week', strtotime($selected_date)));
                            $end_week = date('F j, Y', strtotime('sunday this week', strtotime($selected_date)));
                            echo $start_week . ' - ' . $end_week;
                        ?> • 
                        <?php echo $doctor_filter === 'all' ? 'All Doctors' : htmlspecialchars($doctor_names[$doctor_filter] ?? 'Filtered'); ?>
                    </p>

                    <div class="week-view-grid">
                        <div class="week-time-header"></div>
                        <?php foreach ($week_days as $day): ?>
                            <div class="week-day-header">
                                <div><?php echo $day['day_name']; ?></div>
                                <div><?php echo $day['day_number']; ?></div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php
                        // Time slots for Week View
                        $time_slots = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
                        
                        foreach ($time_slots as $slot_start):
                        ?>
                            <div class="week-time-slot"><?php echo date('g:i A', strtotime($slot_start)); ?></div>
                            
                            <?php foreach ($week_days as $day): 
                                $day_appointments = $day['appointments'];
                                $slot_appointments = array_filter($day_appointments, function($apt) use ($slot_start) {
                                    $apt_hour = date('H', strtotime($apt['appointment_time']));
                                    $slot_hour = date('H', strtotime($slot_start));
                                    return $apt_hour === $slot_hour;
                                });
                            ?>
                                <div class="week-day-cell">
                                    <?php foreach ($slot_appointments as $apt): 
                                        $status_class = strtolower($apt['status']);
                                    ?>
                                        <div class="week-appointment <?php echo $status_class; ?>" title="<?php echo htmlspecialchars($apt['patient_name'] . ' - Dr. ' . $apt['doctor_name']); ?>">
                                            <?php echo date('g:i', strtotime($apt['appointment_time'])); ?> - <?php echo htmlspecialchars($apt['patient_name']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>
                
                <?php elseif ($view === 'month'): ?>
                    <h2 class="schedule-title">Monthly Schedule</h2>
                    <p class="schedule-subtitle">
                        Schedule for <?php echo date('F Y', strtotime($selected_date)); ?> • 
                        <?php echo $doctor_filter === 'all' ? 'All Doctors' : htmlspecialchars($doctor_names[$doctor_filter] ?? 'Filtered'); ?>
                    </p>
                    
                    <div class="month-view-grid">
                        <?php $days_of_week = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        foreach ($days_of_week as $day): ?>
                            <div class="month-day-header"><?php echo substr($day, 0, 3); ?></div>
                        <?php endforeach; ?>
                        
                        <?php
                        // PHP logic to generate month grid days
                        $month_start = date('Y-m-01', strtotime($selected_date));
                        $first_day_of_month_w = date('w', strtotime($month_start)); // 0 (Sun) to 6 (Sat)
                        $days_in_month = date('t', strtotime($selected_date));
                        $current_month = date('Y-m', strtotime($selected_date));
                        
                        // Previous month days
                        $prev_month_start = date('Y-m-01', strtotime('-1 month', strtotime($month_start)));
                        $prev_month_days = date('t', strtotime($prev_month_start));
                        for ($i = $first_day_of_month_w - 1; $i >= 0; $i--) {
                            $day = $prev_month_days - $i;
                            echo '<div class="month-day-cell other-month">';
                            echo '<div class="month-day-number">' . $day . '</div>';
                            echo '</div>';
                        }
                        
                        // Current month days
                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $date_str = date('Y-m-d', strtotime("$month_start +".($day-1)." days"));
                            $day_appointments = $appointments_by_date[$date_str] ?? [];
                            $is_today = $date_str === date('Y-m-d') ? 'today' : '';
                            
                            echo '<div class="month-day-cell ' . $is_today . '" onclick="selectDate(\'' . $date_str . '\')">';
                            echo '<div class="month-day-number">' . $day . '</div>';
                            
                            $visible_appointments = 2; // Show up to 2 appointments before truncation
                            $count = 0;
                            foreach ($day_appointments as $apt):
                                if ($count < $visible_appointments):
                                    $status_class = strtolower($apt['status']);
                                    echo '<div class="month-appointment ' . $status_class . '" title="' . htmlspecialchars($apt['patient_name'] . ' - Dr. ' . $apt['doctor_name']) . '">';
                                    echo date('g:i', strtotime($apt['appointment_time'])) . ' ' . htmlspecialchars(explode(' ', $apt['patient_name'])[0]);
                                    echo '</div>';
                                endif;
                                $count++;
                            endforeach;
                            
                            if (count($day_appointments) > $visible_appointments) {
                                $remaining_count = count($day_appointments) - $visible_appointments;
                                echo '<div class="month-appointment" style="background: #f1f5f9; color: #64748b; text-align: center; border-left: none;">';
                                echo '+'.$remaining_count.' more';
                                echo '</div>';
                            }
                            
                            echo '</div>';
                        }
                        
                        // Next month days
                        $total_cells = 42; // 6 rows x 7 days
                        $cells_used = $first_day_of_month_w + $days_in_month;
                        $remaining_cells = $total_cells - $cells_used;
                        
                        for ($i = 1; $i <= $remaining_cells; $i++) {
                            echo '<div class="month-day-cell other-month">';
                            echo '<div class="month-day-number">' . $i . '</div>';
                            echo '</div>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    function updateUrl(date, view, doctor) {
        const url = new URL(window.location.href);
        url.searchParams.set('date', date);
        url.searchParams.set('view', view);
        url.searchParams.set('doctor', doctor);
        window.location.href = url.toString();
    }

    function selectDate(date) {
        updateUrl(date, '<?php echo $view; ?>', '<?php echo $doctor_filter; ?>');
    }

    function changeView(view) {
        updateUrl('<?php echo $selected_date; ?>', view, '<?php echo $doctor_filter; ?>');
    }

    function filterByDoctor(doctorId) {
        updateUrl('<?php echo $selected_date; ?>', '<?php echo $view; ?>', doctorId);
    }

    function navigateDate(direction) {
        const currentDate = new Date('<?php echo $selected_date; ?>');
        let newDate;
        
        if ('<?php echo $view; ?>' === 'month') {
            newDate = new Date(currentDate);
            newDate.setMonth(newDate.getMonth() + direction);
        } else if ('<?php echo $view; ?>' === 'week') {
            newDate = new Date(currentDate);
            newDate.setDate(newDate.getDate() + (direction * 7));
        } else { // day view
            newDate = new Date(currentDate);
            newDate.setDate(newDate.getDate() + direction);
        }

        const dateString = newDate.toISOString().split('T')[0];
        updateUrl(dateString, '<?php echo $view; ?>', '<?php echo $doctor_filter; ?>');
    }

    function changeMonth(direction) {
        const currentDate = new Date('<?php echo $selected_date; ?>');
        const newDate = new Date(currentDate);
        newDate.setMonth(newDate.getMonth() + direction);
        
        // Reset to the first of the month to keep the calendar consistent
        newDate.setDate(1); 
        
        const dateString = newDate.toISOString().split('T')[0];
        updateUrl(dateString, '<?php echo $view; ?>', '<?php echo $doctor_filter; ?>');
    }
</script>

</body>
</html>