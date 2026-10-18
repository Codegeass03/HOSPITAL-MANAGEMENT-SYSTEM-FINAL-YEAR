<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
// Assuming 'includes/sidebar.php' contains the general site structure up to the main content div start

// --- PHP Logic (kept as-is) ---
// Get current date or selected date
$selected_date = $_GET['date'] ?? date('Y-m-d');
$view = $_GET['view'] ?? 'list'; // Default to 'list' view as it's the one displayed in the image
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

// Get appointments based on view and filters (Assuming the provided function is correct)
function getAppointments($pdo, $date, $view, $doctor_filter) {
    $where_doctor = $doctor_filter !== 'all' ? "AND a.doctor_id = :doctor_id" : "";
    
    // Determine start and end dates based on view
    $start_date = $date;
    $end_date = $date;
    $date_clause = "a.appointment_date = :start";

    if ($view === 'week') {
        // Adjust $date to be within the desired week if necessary (PHP logic already calculates start/end)
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

// Organize appointments by date for month view efficiency
$appointments_by_date = [];
foreach ($appointments as $apt) {
    $appointments_by_date[$apt['appointment_date']][] = $apt;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Schedule - MediCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* --- General CSS (kept as-is) --- */
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

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
            text-decoration: none;
            font-size: 0.875rem;
            margin-bottom: 1rem;
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

        /* Time Slots (Day View) */
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

        /* List View styles (kept as-is) */
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
            /* Assuming yellow for in-progress/scheduled, can be dynamic */
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
            /* Placeholder for image */
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
        
        /* Week and Month View Styles (Modified for uniformity) */
        
        /* The container for the Month Grid */
        .month-view-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            border-left: 1px solid #e2e8f0; /* Simulate left border for the grid */
            border-top: 1px solid #e2e8f0; /* Simulate top border for the grid */
            border-radius: 0.5rem;
            overflow: hidden;
            width: 100%;
        }

        /* The cell for each day of the month */
        .month-day-cell {
            min-height: 120px; /* Enforce a minimum height for uniform boxes */
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

        /* Day number styling */
        .month-day-number {
            font-size: 0.875rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
            text-align: right;
        }

        /* Other month days are visually muted */
        .month-day-cell.other-month {
            background-color: #f1f5f9;
        }
        
        .month-day-cell.other-month .month-day-number {
            color: #94a3b8;
        }

        /* Appointment card within the month cell */
        .month-appointment {
            font-size: 0.75rem;
            color: #0f172a;
            padding: 0.25rem 0.5rem;
            margin-bottom: 0.25rem;
            border-radius: 0.25rem;
            border-left: 3px solid;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        
        .month-day-header {
            text-align: center;
            font-size: 0.875rem;
            color: #0f172a;
            font-weight: 600;
            padding: 0.75rem;
            background: #f1f5f9;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .month-day-header:last-child {
            border-right: none;
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
        <h1>Doctor Schedule</h1>
        <p>Manage and view doctor schedules and appointments</p>
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
                        // PHP logic to generate sidebar calendar days (kept as-is)
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
                        if ($remaining < 7) $remaining += 7; // Ensure 6 weeks if necessary, though 5 is often enough
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
                Add Appointment
            </button>
        </div>

        <div class="schedule-main">
            <div class="schedule-header">
                <div class="view-tabs">
                    <button class="view-tab <?php echo $view === 'day' ? 'active' : ''; ?>" onclick="changeView('day')">Day</button>
                    <button class="view-tab <?php echo $view === 'month' ? 'active' : ''; ?>" onclick="changeView('month')">Month</button>
                    <button class="view-tab <?php echo $view === 'list' ? 'active' : ''; ?>" onclick="changeView('list')">List</button>
                </div>

                <div class="date-navigation">
                    <button class="nav-btn" onclick="navigateDate(-1)"><i class="fas fa-chevron-left"></i></button>
                    <span class="current-date"><?php echo date('F j, Y', strtotime($selected_date)); ?></span>
                    <button class="nav-btn" onclick="navigateDate(1)"><i class="fas fa-chevron-right"></i></button>
                </div>
            </div>

            <div class="schedule-content">
                <?php if ($view === 'list'): ?>
                    <h2 class="schedule-title">Appointments List</h2>
                    <p class="schedule-subtitle">
                        All appointments for <?php echo date('F j, Y', strtotime($selected_date)); ?> • 
                        <?php echo $doctor_filter === 'all' ? 'All Doctors' : htmlspecialchars($doctor_names[$doctor_filter] ?? 'Filtered Doctor'); ?>
                    </p>

                    <div class="appointments-list">
                        <?php if (empty($appointments)): ?>
                            <div class="no-appointments">No appointments scheduled for this date.</div>
                        <?php else: ?>
                            <?php foreach ($appointments as $apt): 
                                $status_class = strtolower($apt['status']);
                                $status_text = ucwords(str_replace('_', ' ', $status_class));
                            ?>
                                <div class="list-appointment-card <?php echo $status_class; ?>">
                                    <div class="list-patient-info">
                                        <div class="color-indicator"></div>
                                        <div class="list-patient-details">
                                            <h4><?php echo htmlspecialchars($apt['patient_name']); ?></h4>
                                            <div class="list-time">
                                                <i class="fas fa-clock"></i>
                                                <span>
                                                    <?php echo date('H:i', strtotime($apt['appointment_time'])); ?> - 
                                                    <?php echo date('H:i', strtotime($apt['appointment_time'] . ' +' . $apt['duration'] . ' minutes')); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="list-doctor-status">
                                        <div class="list-doctor">
                                            <div class="list-doctor-avatar">
                                                <i class="fas fa-user-md"></i>
                                            </div>
                                            <span class="list-doctor-name">Dr. <?php echo htmlspecialchars(explode(' ', $apt['doctor_name'])[1] ?? $apt['doctor_name']); ?></span>
                                        </div>
                                        <span class="status-badge status-<?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                <?php elseif ($view === 'day'): ?>
                    <h2 class="schedule-title">Daily Schedule</h2>
                    <p class="schedule-subtitle">
                        Schedule for <?php echo date('F j, Y', strtotime($selected_date)); ?> • 
                        <?php echo $doctor_filter === 'all' ? 'All Doctors' : htmlspecialchars($doctor_names[$doctor_filter] ?? 'Filtered'); ?>
                    </p>

                    <div class="time-slots">
                        <?php
                        // Example time slots for Day View (can be expanded)
                        $time_slots = ['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];
                        
                        foreach ($time_slots as $slot_start):
                            $hour = (int)date('H', strtotime($slot_start));
                            $slot_appointments = array_filter($appointments, function($apt) use ($hour) {
                                $apt_hour = (int)date('H', strtotime($apt['appointment_time']));
                                return $apt_hour === $hour;
                            });
                        ?>
                            <div class="time-slot">
                                <div class="time-label"><?php echo date('g A', strtotime($slot_start)); ?></div>
                                <div class="slot-content">
                                    <?php if (empty($slot_appointments)): ?>
                                        <div class="no-appointments">Available</div>
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
                
                <?php elseif ($view === 'month'): ?>
                    <h2 class="schedule-title">Monthly Schedule</h2>
                    <p class="schedule-subtitle">
                        Schedule for <?php echo date('F Y', strtotime($selected_date)); ?> • 
                        <?php echo $doctor_filter === 'all' ? 'All Doctors' : htmlspecialchars($doctor_names[$doctor_filter] ?? 'Filtered'); ?>
                    </p>
                    <div class="month-view-grid">
                        <?php $days_of_week = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        foreach ($days_of_week as $day): ?>
                            <div class="month-day-header"><?php echo substr($day, 0, 2); ?></div>
                        <?php endforeach; ?>
                        
                        <?php
                        // PHP logic to generate month grid days
                        $month_start = date('Y-m-01', strtotime($selected_date));
                        $first_day_of_month_w = date('w', strtotime($month_start)); // 0 (Sun) to 6 (Sat)
                        $days_in_month = date('t', strtotime($selected_date));
                        $current_month = date('Y-m', strtotime($selected_date));
                        $cell_count = 0;
                        
                        // Previous month days
                        $prev_month_start = date('Y-m-01', strtotime('-1 month', strtotime($month_start)));
                        $prev_month_days = date('t', strtotime($prev_month_start));
                        for ($i = $first_day_of_month_w - 1; $i >= 0; $i--) {
                            $day = $prev_month_days - $i;
                            echo '<div class="month-day-cell other-month">';
                            echo '<div class="month-day-number">' . $day . '</div>';
                            echo '</div>';
                            $cell_count++;
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
                                    $color = $status_class == 'cancelled' ? '#ef4444' : ($status_class == 'in_progress' ? '#fbbf24' : '#3b82f6');
                                    $bg_color = $status_class == 'cancelled' ? '#fef2f2' : ($status_class == 'in_progress' ? '#fffbeb' : '#eff6ff');

                                    echo '<div class="month-appointment" style="border-left-color: ' . $color . '; background: ' . $bg_color . ';" title="' . htmlspecialchars($apt['patient_name'] . ' - Dr. ' . explode(' ', $apt['doctor_name'])[1] ?? $apt['doctor_name']) . '">';
                                    echo date('g:i', strtotime($apt['appointment_time'])) . ' ' . htmlspecialchars(explode(' ', $apt['patient_name'])[0]);
                                    echo '</div>';
                                endif;
                                $count++;
                            endforeach;
                            
                            if (count($day_appointments) > $visible_appointments) {
                                $remaining_count = count($day_appointments) - $visible_appointments;
                                echo '<div class="month-appointment" style="background: #f1f5f9; color: #64748b; text-align: center; border-left: none;">';
                                echo ' +'.$remaining_count.' more';
                                echo '</div>';
                            }
                            
                            echo '</div>';
                            $cell_count++;
                        }
                        
                        // Next month days
                        $i = 1;
                        while ($cell_count % 7 !== 0) {
                            echo '<div class="month-day-cell other-month">';
                            echo '<div class="month-day-number">' . $i . '</div>';
                            echo '</div>';
                            $i++;
                            $cell_count++;
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    const currentDate = new Date('<?php echo $selected_date; ?>');

    function updateUrl(date, view, doctor) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('date', date);
        urlParams.set('view', view);
        urlParams.set('doctor', doctor);
        window.location.search = urlParams.toString();
    }

    function selectDate(date) {
        updateUrl(date, '<?php echo $view; ?>', '<?php echo $doctor_filter; ?>');
    }

    function changeMonth(delta) {
        const newDate = new Date(currentDate);
        newDate.setMonth(newDate.getMonth() + delta);
        updateUrl(newDate.toISOString().split('T')[0], '<?php echo $view; ?>', '<?php echo $doctor_filter; ?>');
    }
    
    function navigateDate(delta) {
        const newDate = new Date(currentDate);
        let newDateString;

        if ('<?php echo $view; ?>' === 'month') {
            newDate.setMonth(newDate.getMonth() + delta);
            newDateString = newDate.toISOString().split('T')[0];
        } else if ('<?php echo $view; ?>' === 'day' || '<?php echo $view; ?>' === 'list') {
            newDate.setDate(newDate.getDate() + delta);
            newDateString = newDate.toISOString().split('T')[0];
        } 
        
        updateUrl(newDateString, '<?php echo $view; ?>', '<?php echo $doctor_filter; ?>');
    }

    function changeView(newView) {
        // When changing view from month, revert date to just a day in that month, usually the first
        let newDate = '<?php echo $selected_date; ?>';
        if (newView === 'month') {
            newDate = '<?php echo date('Y-m-01', strtotime($selected_date)); ?>';
        } else if (newView === 'day' && '<?php echo $view; ?>' === 'month') {
            // Keep the selected date if switching from month to day, or default to current date
            newDate = new Date().toISOString().split('T')[0];
        }

        updateUrl(newDate, newView, '<?php echo $doctor_filter; ?>');
    }

    function filterByDoctor(doctorId) {
        updateUrl('<?php echo $selected_date; ?>', '<?php echo $view; ?>', doctorId);
    }
    
    // Auto-select view logic for Month View navigation:
    document.addEventListener('DOMContentLoaded', () => {
        // If we're in month view, the date navigation should operate on a month basis
        // This is handled by the navigateDate function based on the current $view, so no DOM manipulation is needed here.
    });

</script>
</body>
</html>