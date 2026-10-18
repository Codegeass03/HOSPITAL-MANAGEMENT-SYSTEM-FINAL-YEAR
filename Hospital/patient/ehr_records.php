<?php
session_start();

if(isset($_SESSION["user"])){
    if(($_SESSION["user"])=="" or $_SESSION['usertype']!='p'){
        header("location: ../login.php");
        exit();
    }else{
        $useremail=$_SESSION["user"];
    }
}else{
    header("location: ../login.php");
    exit();
}

include("../connection.php");

// Get patient information
$sqlmain = "select * from patient where pemail=?";
$stmt = $database->prepare($sqlmain);
$stmt->bind_param("s",$useremail);
$stmt->execute();
$result = $stmt->get_result();
$userfetch=$result->fetch_assoc();
$userid= $userfetch["pid"];
$username=$userfetch["pname"];

// Get latest vitals for overview
$latest_vitals_query = "SELECT 
    vitals_bp, vitals_temp, vitals_hr, vitals_rr, vitals_weight, vitals_height,
    DATE(created_at) as record_date
    FROM ehr_records 
    WHERE pid = ? AND (
        vitals_bp IS NOT NULL OR 
        vitals_temp IS NOT NULL OR 
        vitals_hr IS NOT NULL OR 
        vitals_rr IS NOT NULL OR 
        vitals_weight IS NOT NULL OR 
        vitals_height IS NOT NULL
    )
    ORDER BY created_at DESC 
    LIMIT 1";
$stmt = $database->prepare($latest_vitals_query);
$stmt->bind_param("i", $userid);
$stmt->execute();
$latest_vitals = $stmt->get_result()->fetch_assoc();

// Get all EHR records for the patient with appointment and doctor details
$ehr_query = "SELECT 
    e.id as ehr_id,
    e.appoid,
    e.diagnosis,
    e.treatment_plan,
    e.vitals_bp,
    e.vitals_temp,
    e.vitals_hr,
    e.vitals_rr,
    e.vitals_weight,
    e.vitals_height,
    e.created_at,
    a.appodate,
    a.apponum,
    d.docname,
    sp.sname as specialty,
    (SELECT COUNT(*) FROM ehr_prescriptions WHERE appoid = e.appoid) as prescription_count
FROM ehr_records e
INNER JOIN appointment a ON e.appoid = a.appoid
INNER JOIN doctor d ON e.docid = d.docid
LEFT JOIN specialties sp ON d.specialties = sp.id
WHERE e.pid = ?
ORDER BY a.appodate DESC, e.created_at DESC";

$stmt = $database->prepare($ehr_query);
$stmt->bind_param("i", $userid);
$stmt->execute();
$ehr_result = $stmt->get_result();

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_visits,
    COUNT(DISTINCT d.docid) as doctors_seen,
    MIN(a.appodate) as first_visit,
    MAX(a.appodate) as last_visit
    FROM ehr_records e
    INNER JOIN appointment a ON e.appoid = a.appoid
    INNER JOIN doctor d ON e.docid = d.docid
    WHERE e.pid = ?";
$stmt = $database->prepare($stats_query);
$stmt->bind_param("i", $userid);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Store ehr_result for later use since we're going to use it multiple times
$ehr_data = [];
while($row = $ehr_result->fetch_assoc()) {
    $ehr_data[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Medical Records</title>
    <style>
        :root {
            --primarycolor: #0891b2;
            --primarydark: #0e7490;
            --primarylight: #06b6d4;
            --secondarycolor: #14b8a6;
            --accentcolor: #22d3ee;
            --bgcolor: #f0fdfa;
            --textcolor: #134e4a;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bgcolor);
            color: var(--textcolor);
            line-height: 1.5;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .menu {
            width: 280px;
            background: linear-gradient(135deg, var(--primarycolor) 0%, var(--primarydark) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .profile-container {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .profile-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .profile-subtitle {
            font-size: 14px;
            opacity: 0.9;
        }

        .logout-btn {
            width: 100%;
            padding: 10px;
            margin-top: 15px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        
        .menu-row {
            margin: 5px 0;
        }

        .menu-btn {
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .menu-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        .menu-btn.menu-active {
            background: rgba(255,255,255,0.2);
            border-left: 4px solid white;
        }

        .menu-text {
            font-size: 15px;
            color: white;
            text-decoration: none;
        }

               .menu-icon-home::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'/%3E%3Cpolyline points='9 22 9 12 15 12 15 22'/%3E%3C/svg%3E");
        }

        .menu-icon-doctor::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'/%3E%3Ccircle cx='8.5' cy='7' r='4'/%3E%3Cline x1='20' y1='8' x2='20' y2='14'/%3E%3Cline x1='23' y1='11' x2='17' y2='11'/%3E%3C/svg%3E");
        }

        .menu-icon-session::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cline x1='16' y1='2' x2='16' y2='6'/%3E%3Cline x1='8' y1='2' x2='8' y2='6'/%3E%3Cline x1='3' y1='10' x2='21' y2='10'/%3E%3C/svg%3E");
        }

        .menu-icon-appoinment::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3Cpolyline points='12 6 12 12 16 14'/%3E%3C/svg%3E");
        }

       .menu-icon-medical::before {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'/%3E%3Cpolyline points='14 2 14 8 20 8'/%3E%3Cpath d='M16 13H8'/%3E%3Cpath d='M16 17H8'/%3E%3Cpath d='M10 9H9H8'/%3E%3Ccircle cx='16' cy='9' r='1'/%3E%3C/svg%3E");
}

        .menu-icon-settings::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Ccircle cx='12' cy='12' r='3'/%3E%3Cpath d='M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z'/%3E%3C/svg%3E");
        }


        .non-style-link-menu {
            text-decoration: none;
            color: white;
            display: block;
        }

        /* Main Content */
        .dash-body {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            animation: fadeIn 0.5s ease;
        }

        /* Header */
        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            color: var(--primarycolor);
            margin-bottom: 8px;
        }

        .header p {
            color: #64748b;
            font-size: 16px;
        }

        /* Overview Cards */
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .overview-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(8, 145, 178, 0.1);
            transition: transform 0.3s ease;
        }

        .overview-card:hover {
            transform: translateY(-5px);
        }

        .card-title {
            font-size: 14px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
        }

        .card-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--primarycolor);
            margin-bottom: 5px;
        }

        .card-subtitle {
            font-size: 14px;
            color: #94a3b8;
        }

        /* Vitals Overview */
        .vitals-overview {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(8, 145, 178, 0.1);
        }

        .vitals-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .vital-item {
            text-align: center;
            padding: 20px 15px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .vital-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .vital-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primarycolor);
        }

        .vital-unit {
            font-size: 12px;
            color: #94a3b8;
            margin-left: 4px;
        }

        .no-vitals {
            text-align: center;
            padding: 30px;
            color: #94a3b8;
        }

        /* Records List */
        .records-section {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(8, 145, 178, 0.1);
        }

        .records-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .records-count {
            background: var(--accentcolor);
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        /* Record Cards */
        .record-card {
            background: #f8fafc;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primarycolor);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .record-card:hover {
            background: #f0f9ff;
            transform: translateX(5px);
        }

        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .record-date {
            font-size: 16px;
            font-weight: 600;
            color: var(--textcolor);
        }

        .appointment-badge {
            background: var(--primarycolor);
            color: white;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .doctor-info {
            margin-bottom: 15px;
        }

        .doctor-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--primarydark);
            margin-bottom: 4px;
        }

        .specialty {
            font-size: 14px;
            color: #64748b;
        }

        .record-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .preview-item {
            background: white;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .preview-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .preview-content {
            font-size: 14px;
            color: var(--textcolor);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .record-vitals {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }

        .vital-preview {
            text-align: center;
            padding: 8px;
            background: white;
            border-radius: 6px;
            border: 1px solid var(--border-color);
        }

        .vital-preview-label {
            font-size: 11px;
            color: #94a3b8;
            margin-bottom: 2px;
        }

        .vital-preview-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--primarycolor);
        }

        .record-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
            font-size: 13px;
            color: #94a3b8;
        }

        .prescription-count {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .expand-btn {
            background: transparent;
            border: 1px solid var(--primarycolor);
            color: var(--primarycolor);
            padding: 6px 15px;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .expand-btn:hover {
            background: var(--primarycolor);
            color: white;
        }

        /* Expanded View */
        .expanded-view {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.5s ease;
            margin-top: 15px;
        }

        .expanded-view.active {
            max-height: 2000px;
        }

        .expanded-section {
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--primarydark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-content {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .prescriptions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }

        .prescription-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            border-left: 4px solid var(--secondarycolor);
        }

        .medication-name {
            font-weight: 600;
            color: var(--textcolor);
            margin-bottom: 8px;
        }

        .prescription-details {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 13px;
            color: #64748b;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-icon {
            font-size: 72px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--textcolor);
            margin-bottom: 10px;
        }

        .empty-subtitle {
            color: #64748b;
            margin-bottom: 25px;
            max-width: 500px;
            margin: 0 auto 25px;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--primarycolor);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: var(--primarydark);
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            color: var(--primarycolor);
            border: 2px solid var(--primarycolor);
        }

        .btn-outline:hover {
            background: var(--primarycolor);
            color: white;
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .menu {
                width: 240px;
            }
            .dash-body {
                margin-left: 240px;
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            .menu {
                position: relative;
                width: 100%;
                height: auto;
            }
            .dash-body {
                margin-left: 0;
                padding: 15px;
            }
            .overview-grid {
                grid-template-columns: 1fr;
            }
            .record-header {
                flex-direction: column;
                gap: 10px;
            }
            .record-preview {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
  <div class="container">
        <!-- Sidebar Menu -->
        <div class="menu">
            <table class="menu-container" border="0">
                <tr>
                    <td style="padding:10px" colspan="2">
                        <table border="0" class="profile-container">
                            <tr>
                                <td width="30%" style="padding-left:20px">
                                    <img src="../img/user.png" alt="" width="100%" style="border-radius:50%">
                                </td>
                                <td style="padding:0px;margin:0px;">
                                    <p class="profile-title"><?php echo substr($username,0,13)  ?>..</p>
                                    <p class="profile-subtitle"><?php echo substr($useremail,0,22)  ?></p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <a href="../logout.php"><input type="button" value="Log out" class="logout-btn"></a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
              <tr class="menu-row">
                    <td class="menu-btn menu-icon-home menu-active">
                        <a href="index.php" class="non-style-link-menu"><p class="menu-text">Home</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor">
                        <a href="doctors.php" class="non-style-link-menu"><p class="menu-text">All Doctors</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-session">
                        <a href="schedule.php" class="non-style-link-menu"><p class="menu-text">Scheduled Sessions</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appoinment">
                        <a href="appointment.php" class="non-style-link-menu"><p class="menu-text">My Bookings</p></a>
                    </td>
                </tr>
                  <tr class="menu-row">
                    <td class="menu-btn menu-icon-medical">
                        <a href="ehr_records.php" class="non-style-link-menu"><p class="menu-text">Medical Records</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-settings">
                        <a href="settings.php" class="non-style-link-menu"><p class="menu-text">Settings</p></a>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Main Content -->
        <div class="dash-body">
            <!-- Header -->
            <div class="header">
                <h1>My Medical Records</h1>
                <p>View your complete health history and medical information</p>
            </div>

            <!-- Overview Stats -->
            <div class="overview-grid">
                <div class="overview-card">
                    <div class="card-title">Total Visits</div>
                    <div class="card-value"><?php echo $stats['total_visits'] ?? 0; ?></div>
                    <div class="card-subtitle">Medical consultations</div>
                </div>
                <div class="overview-card">
                    <div class="card-title">Doctors Seen</div>
                    <div class="card-value"><?php echo $stats['doctors_seen'] ?? 0; ?></div>
                    <div class="card-subtitle">Healthcare professionals</div>
                </div>
                <div class="overview-card">
                    <div class="card-title">First Visit</div>
                    <div class="card-value">
                        <?php echo $stats['first_visit'] ? date('M Y', strtotime($stats['first_visit'])) : '--'; ?>
                    </div>
                    <div class="card-subtitle">Patient since</div>
                </div>
                <div class="overview-card">
                    <div class="card-title">Last Visit</div>
                    <div class="card-value">
                        <?php echo $stats['last_visit'] ? date('M Y', strtotime($stats['last_visit'])) : '--'; ?>
                    </div>
                    <div class="card-subtitle">Most recent appointment</div>
                </div>
            </div>

            <!-- Vitals Overview -->
            <div class="vitals-overview">
                <div class="vitals-header">
                    <h2>Latest Vital Signs</h2>
                    <?php if($latest_vitals && $latest_vitals['record_date']): ?>
                        <span class="card-subtitle">Recorded: <?php echo date('M j, Y', strtotime($latest_vitals['record_date'])); ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if($latest_vitals && ($latest_vitals['vitals_bp'] || $latest_vitals['vitals_temp'] || $latest_vitals['vitals_hr'] || $latest_vitals['vitals_rr'] || $latest_vitals['vitals_weight'] || $latest_vitals['vitals_height'])): ?>
                    <div class="vitals-grid">
                        <?php if($latest_vitals['vitals_bp']): ?>
                            <div class="vital-item">
                                <div class="vital-label">Blood Pressure</div>
                                <div class="vital-value"><?php echo htmlspecialchars($latest_vitals['vitals_bp']); ?><span class="vital-unit">mmHg</span></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($latest_vitals['vitals_temp']): ?>
                            <div class="vital-item">
                                <div class="vital-label">Temperature</div>
                                <div class="vital-value"><?php echo htmlspecialchars($latest_vitals['vitals_temp']); ?><span class="vital-unit">°C</span></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($latest_vitals['vitals_hr']): ?>
                            <div class="vital-item">
                                <div class="vital-label">Heart Rate</div>
                                <div class="vital-value"><?php echo htmlspecialchars($latest_vitals['vitals_hr']); ?><span class="vital-unit">bpm</span></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($latest_vitals['vitals_rr']): ?>
                            <div class="vital-item">
                                <div class="vital-label">Respiratory Rate</div>
                                <div class="vital-value"><?php echo htmlspecialchars($latest_vitals['vitals_rr']); ?><span class="vital-unit">/min</span></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($latest_vitals['vitals_weight']): ?>
                            <div class="vital-item">
                                <div class="vital-label">Weight</div>
                                <div class="vital-value"><?php echo htmlspecialchars($latest_vitals['vitals_weight']); ?><span class="vital-unit">kg</span></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if($latest_vitals['vitals_height']): ?>
                            <div class="vital-item">
                                <div class="vital-label">Height</div>
                                <div class="vital-value"><?php echo htmlspecialchars($latest_vitals['vitals_height']); ?><span class="vital-unit">cm</span></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="no-vitals">
                        <p>No vital signs recorded yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Records List -->
            <div class="records-section">
                <div class="records-header">
                    <h2>Medical History</h2>
                    <span class="records-count"><?php echo count($ehr_data); ?> records</span>
                </div>

                <?php if(count($ehr_data) > 0): ?>
                    <?php foreach($ehr_data as $record): 
                        // Get detailed information for each record
                        $presc_query = "SELECT * FROM ehr_prescriptions WHERE appoid = ?";
                        $presc_stmt = $database->prepare($presc_query);
                        $presc_stmt->bind_param("i", $record['appoid']);
                        $presc_stmt->execute();
                        $prescriptions = $presc_stmt->get_result();
                    ?>
                    
                    <div class="record-card" onclick="toggleRecord(<?php echo $record['ehr_id']; ?>)">
                        <div class="record-header">
                            <div class="record-date"><?php echo date('F j, Y', strtotime($record['appodate'])); ?></div>
                            <span class="appointment-badge">Appointment #<?php echo $record['apponum']; ?></span>
                        </div>
                        
                        <div class="doctor-info">
                            <div class="doctor-name">Dr. <?php echo htmlspecialchars($record['docname']); ?></div>
                            <?php if($record['specialty']): ?>
                                <div class="specialty"><?php echo htmlspecialchars($record['specialty']); ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Diagnosis and Treatment Preview -->
                        <div class="record-preview">
                            <?php if($record['diagnosis']): ?>
                            <div class="preview-item">
                                <div class="preview-label">Diagnosis</div>
                                <div class="preview-content"><?php echo htmlspecialchars(substr($record['diagnosis'], 0, 100)); ?><?php echo strlen($record['diagnosis']) > 100 ? '...' : ''; ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- <?php if($record['treatment_plan']): ?>
                            <div class="preview-item">
                                <div class="preview-label">Treatment</div>
                                <div class="preview-content"><?php echo htmlspecialchars(substr($record['treatment_plan'], 0, 100)); ?><?php echo strlen($record['treatment_plan']) > 100 ? '...' : ''; ?></div>
                            </div>
                            <?php endif; ?> -->
                        </div>

                        <!-- Vitals Preview -->
                        <?php 
                        $has_vitals = $record['vitals_bp'] || $record['vitals_temp'] || $record['vitals_hr'] || 
                                     $record['vitals_rr'] || $record['vitals_weight'] || $record['vitals_height'];
                        if($has_vitals): 
                        ?>
                        <div class="record-vitals">
                            <?php if($record['vitals_bp']): ?>
                            <div class="vital-preview">
                                <div class="vital-preview-label">BP</div>
                                <div class="vital-preview-value"><?php echo htmlspecialchars($record['vitals_bp']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($record['vitals_temp']): ?>
                            <div class="vital-preview">
                                <div class="vital-preview-label">Temp</div>
                                <div class="vital-preview-value"><?php echo htmlspecialchars($record['vitals_temp']); ?>°</div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($record['vitals_hr']): ?>
                            <div class="vital-preview">
                                <div class="vital-preview-label">HR</div>
                                <div class="vital-preview-value"><?php echo htmlspecialchars($record['vitals_hr']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($record['vitals_rr']): ?>
                            <div class="vital-preview">
                                <div class="vital-preview-label">RR</div>
                                <div class="vital-preview-value"><?php echo htmlspecialchars($record['vitals_rr']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($record['vitals_weight']): ?>
                            <div class="vital-preview">
                                <div class="vital-preview-label">Weight</div>
                                <div class="vital-preview-value"><?php echo htmlspecialchars($record['vitals_weight']); ?>kg</div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($record['vitals_height']): ?>
                            <div class="vital-preview">
                                <div class="vital-preview-label">Height</div>
                                <div class="vital-preview-value"><?php echo htmlspecialchars($record['vitals_height']); ?>cm</div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <div class="record-footer">
                            <div class="prescription-count">
                                <span>💊</span>
                                <span><?php echo $record['prescription_count']; ?> prescriptions</span>
                            </div>
                            <div>
                                <span style="color: #94a3b8; font-size: 13px;">
                                    Recorded: <?php echo date('M j, g:i A', strtotime($record['created_at'])); ?>
                                </span>
                                <button class="expand-btn" onclick="toggleRecord(<?php echo $record['ehr_id']; ?>)">View Details</button>
                            </div>
                        </div>

                        <!-- Expanded View (Hidden by default) -->
                        <div id="record-<?php echo $record['ehr_id']; ?>" class="expanded-view">
                            <!-- Diagnosis Details -->
                            <?php if($record['diagnosis']): ?>
                            <div class="expanded-section">
                                <div class="section-title">🩺 Diagnosis</div>
                                <div class="section-content"><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Treatment Plan Details -->
                            <?php if($record['treatment_plan']): ?>
                            <div class="expanded-section">
                                <div class="section-title">💊 Treatment Plan</div>
                                <div class="section-content"><?php echo nl2br(htmlspecialchars($record['treatment_plan'])); ?></div>
                            </div>
                            <?php endif; ?>
                            
                    <!-- Full Vitals -->
<?php if($has_vitals): ?>
<div class="expanded-section">
    <div class="section-title">❤️ Vital Signs</div>
    <div class="section-content">
        <div class="vitals-grid" style="grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));">
            <?php if($record['vitals_bp']): ?>
            <div class="vital-item">
                <div class="vital-label">Blood Pressure</div>
                <div class="vital-value">
                    <?php echo htmlspecialchars($record['vitals_bp']); ?>
                    <span class="vital-unit">mmHg</span>
                </div>
                <?php if(preg_match('/(\d+)\/(\d+)/', $record['vitals_bp'], $matches)): 
                    $systolic = intval($matches[1]);
                    $diastolic = intval($matches[2]);
                    
                    $bp_status = '';
                    if($systolic < 120 && $diastolic < 80) {
                        $bp_status = 'Normal';
                    } elseif($systolic >= 120 && $systolic <= 129 && $diastolic < 80) {
                        $bp_status = 'Elevated';
                    } elseif($systolic >= 130 && $systolic <= 139 || $diastolic >= 80 && $diastolic <= 89) {
                        $bp_status = 'Stage 1 Hypertension';
                    } elseif($systolic >= 140 || $diastolic >= 90) {
                        $bp_status = 'Stage 2 Hypertension';
                    }
                ?>
                <?php if($bp_status): ?>
                <div style="font-size: 11px;color: <?php 
                    echo $bp_status == 'Normal' ? '#10b981' : 
                         ($bp_status == 'Elevated' ? '#f59e0b' : 
                         ($bp_status == 'Stage 1 Hypertension' ? '#f97316' : '#ef4444')); ?>;margin-top: 5px;">
                    <?php echo $bp_status; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if($record['vitals_temp']): ?>
            <div class="vital-item">
                <div class="vital-label">Temperature</div>
                <div class="vital-value">
                    <?php echo htmlspecialchars($record['vitals_temp']); ?>
                    <span class="vital-unit">°C</span>
                </div>
                <?php 
                $temp_value = floatval($record['vitals_temp']);
                $temp_status = '';
                if($temp_value < 35) {
                    $temp_status = 'Hypothermia';
                } elseif($temp_value >= 35 && $temp_value <= 37.5) {
                    $temp_status = 'Normal';
                } elseif($temp_value > 37.5 && $temp_value <= 38.3) {
                    $temp_status = 'Low-grade fever';
                } elseif($temp_value > 38.3) {
                    $temp_status = 'Fever';
                }
                ?>
                <?php if($temp_status): ?>
                <div style="font-size: 11px;color: <?php 
                    echo $temp_status == 'Normal' ? '#10b981' : 
                         ($temp_status == 'Low-grade fever' ? '#f59e0b' : 
                         ($temp_status == 'Fever' ? '#f97316' : '#0891b2')); ?>;margin-top: 5px;">
                    <?php echo $temp_status; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if($record['vitals_hr']): ?>
            <div class="vital-item">
                <div class="vital-label">Heart Rate</div>
                <div class="vital-value">
                    <?php echo htmlspecialchars($record['vitals_hr']); ?>
                    <span class="vital-unit">bpm</span>
                </div>
                <?php 
                $hr_value = intval($record['vitals_hr']);
                $hr_status = '';
                if($hr_value < 60) {
                    $hr_status = 'Bradycardia';
                } elseif($hr_value >= 60 && $hr_value <= 100) {
                    $hr_status = 'Normal';
                } elseif($hr_value > 100) {
                    $hr_status = 'Tachycardia';
                }
                ?>
                <?php if($hr_status): ?>
                <div style="font-size: 11px;color: <?php 
                    echo $hr_status == 'Normal' ? '#10b981' : '#ef4444'; ?>;margin-top: 5px;">
                    <?php echo $hr_status; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if($record['vitals_rr']): ?>
            <div class="vital-item">
                <div class="vital-label">Resp. Rate</div>
                <div class="vital-value">
                    <?php echo htmlspecialchars($record['vitals_rr']); ?>
                    <span class="vital-unit">/min</span>
                </div>
                <?php 
                $rr_value = intval($record['vitals_rr']);
                $rr_status = '';
                if($rr_value < 12) {
                    $rr_status = 'Bradypnea';
                } elseif($rr_value >= 12 && $rr_value <= 20) {
                    $rr_status = 'Normal';
                } elseif($rr_value > 20) {
                    $rr_status = 'Tachypnea';
                }
                ?>
                <?php if($rr_status): ?>
                <div style="font-size: 11px;color: <?php 
                    echo $rr_status == 'Normal' ? '#10b981' : '#ef4444'; ?>;margin-top: 5px;">
                    <?php echo $rr_status; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if($record['vitals_weight']): ?>
            <div class="vital-item">
                <div class="vital-label">Weight</div>
                <div class="vital-value">
                    <?php echo htmlspecialchars($record['vitals_weight']); ?>
                    <span class="vital-unit">kg</span>
                </div>
                <?php if($record['vitals_height']): 
                    $height = floatval($record['vitals_height']);
                    $weight = floatval($record['vitals_weight']);
                    if($height > 0) {
                        $height_m = $height / 100;
                        $bmi = $weight / ($height_m * $height_m);
                        $bmi_status = '';
                        if($bmi < 18.5) {
                            $bmi_status = 'Underweight';
                        } elseif($bmi >= 18.5 && $bmi < 25) {
                            $bmi_status = 'Normal';
                        } elseif($bmi >= 25 && $bmi < 30) {
                            $bmi_status = 'Overweight';
                        } else {
                            $bmi_status = 'Obese';
                        }
                ?>
                <div style="font-size: 11px;color: <?php 
                    echo $bmi_status == 'Normal' ? '#10b981' : 
                         ($bmi_status == 'Underweight' ? '#0891b2' : 
                         ($bmi_status == 'Overweight' ? '#f59e0b' : '#ef4444')); ?>;margin-top: 5px;">
                    BMI: <?php echo number_format($bmi, 1); ?> (<?php echo $bmi_status; ?>)
                </div>
                <?php } endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if($record['vitals_height']): ?>
            <div class="vital-item">
                <div class="vital-label">Height</div>
                <div class="vital-value">
                    <?php echo htmlspecialchars($record['vitals_height']); ?>
                    <span class="vital-unit">cm</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Additional Health Information -->
        <?php if($record['vitals_weight'] && $record['vitals_height']): 
            $height = floatval($record['vitals_height']);
            $weight = floatval($record['vitals_weight']);
            if($height > 0 && $weight > 0):
                $height_m = $height / 100;
                $bmi = $weight / ($height_m * $height_m);
                $ideal_min = 18.5 * ($height_m * $height_m);
                $ideal_max = 24.9 * ($height_m * $height_m);
        ?>
        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
            <div style="font-size: 12px; color: #64748b; margin-bottom: 5px;">Body Mass Index (BMI) Analysis</div>
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
                <div style="font-size: 14px; color: var(--textcolor);">
                    <strong>Current BMI:</strong> <?php echo number_format($bmi, 1); ?>
                </div>
                <div style="font-size: 14px; color: #64748b;">
                    <strong>Healthy Range:</strong> 18.5 - 24.9
                </div>
                <div style="font-size: 14px; color: #64748b;">
                    <strong>Ideal Weight:</strong> <?php echo number_format($ideal_min, 1); ?> - <?php echo number_format($ideal_max, 1); ?> kg
                </div>
            </div>
            
            <!-- BMI Progress Bar -->
            <?php 
            $bmi_percent = ($bmi - 15) / (40 - 15) * 100;
            if($bmi_percent < 0) $bmi_percent = 0;
            if($bmi_percent > 100) $bmi_percent = 100;
            ?>
            <div style="margin-top: 10px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 12px; color: #64748b;">
                    <span>Underweight (<18.5)</span>
                    <span>Normal (18.5-24.9)</span>
                    <span>Overweight (25-29.9)</span>
                    <span>Obese (30+)</span>
                </div>
                <div style="height: 8px; background: linear-gradient(to right, #0891b2, #10b981, #f59e0b, #ef4444); border-radius: 4px; position: relative;">
                    <div style="position: absolute; top: -2px; left: <?php echo $bmi_percent; ?>%; width: 12px; height: 12px; background: white; border: 2px solid var(--textcolor); border-radius: 50%; transform: translateX(-50%);"></div>
                </div>
            </div>
        </div>
        <?php endif; endif; ?>
    </div>
</div>
<?php endif; ?>
                            
                            <!-- Prescriptions -->
                            <?php if($prescriptions->num_rows > 0): ?>
                            <div class="expanded-section">
                                <div class="section-title">💊 Prescriptions (<?php echo $prescriptions->num_rows; ?>)</div>
                                <div class="section-content">
                                    <div class="prescriptions-grid">
                                        <?php while($presc = $prescriptions->fetch_assoc()): ?>
                                        <div class="prescription-card">
                                            <div class="medication-name"><?php echo htmlspecialchars($presc['medication']); ?></div>
                                            <div class="prescription-details">
                                                <?php if($presc['dosage']): ?>
                                                <div>Dosage: <?php echo htmlspecialchars($presc['dosage']); ?></div>
                                                <?php endif; ?>
                                                <?php if($presc['frequency']): ?>
                                                <div>Frequency: <?php echo htmlspecialchars($presc['frequency']); ?></div>
                                                <?php endif; ?>
                                                <?php if($presc['duration']): ?>
                                                <div>Duration: <?php echo htmlspecialchars($presc['duration']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if($presc['instructions']): ?>
                                            <div style="margin-top: 8px; font-size: 13px; color: #64748b;">
                                                <strong>Instructions:</strong> <?php echo htmlspecialchars($presc['instructions']); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endwhile; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📋</div>
                        <h3 class="empty-title">No Medical Records Found</h3>
                        <p class="empty-subtitle">
                            Your medical records will appear here after you complete appointments with doctors.
                            Book your first appointment to start building your medical history.
                        </p>
                        <div class="action-buttons">
                            <a href="doctors.php" class="btn btn-primary">Find a Doctor</a>
                            <a href="schedule.php" class="btn btn-outline">Book Appointment</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Toggle expanded view for records
        function toggleRecord(recordId) {
            event.stopPropagation(); // Prevent event bubbling
            const expandedView = document.getElementById(`record-${recordId}`);
            const button = event.target.closest('.expand-btn') || event.target;
            
            if (expandedView.classList.contains('active')) {
                expandedView.classList.remove('active');
                button.textContent = 'View Details';
                button.style.backgroundColor = 'transparent';
                button.style.color = 'var(--primarycolor)';
            } else {
                // Close other expanded views
                document.querySelectorAll('.expanded-view.active').forEach(view => {
                    if (view.id !== `record-${recordId}`) {
                        view.classList.remove('active');
                        const otherBtn = view.closest('.record-card').querySelector('.expand-btn');
                        if (otherBtn) otherBtn.textContent = 'View Details';
                    }
                });
                
                expandedView.classList.add('active');
                button.textContent = 'Hide Details';
                button.style.backgroundColor = 'var(--primarycolor)';
                button.style.color = 'white';
            }
        }

        // Smooth scroll and animation for cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.record-card');
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            });
            
            cards.forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                observer.observe(card);
            });
        });
    </script>
</body>
</html>