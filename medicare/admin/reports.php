<?php
// reports.php
session_start();

if(isset($_SESSION["user"])){
    if(($_SESSION["user"])=="" or $_SESSION['usertype']!='a'){
        header("location: ../login.php");
    }
}else{
    header("location: ../login.php");
}

include("../connection.php");

// Set default dates
date_default_timezone_set('Africa/Nairobi');
$today = date('Y-m-d');
$thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));

// Get filter parameters
$reportType = isset($_GET['report']) ? $_GET['report'] : 'financial';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : $thirtyDaysAgo;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : $today;
$supplierId = isset($_GET['supplier_id']) ? $_GET['supplier_id'] : '';

// Function to safely execute queries
function fetchData($query, $params = [], $database) {
    $stmt = $database->prepare($query);
    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Smart Clinic Management System</title>
    <style>
        :root {
            --primarycolor: #0891b2;
            --primarydark: #0e7490;
            --primarylight: #06b6d4;
            --secondarycolor: #14b8a6;
            --accentcolor: #22d3ee;
            --bgcolor: #f0fdfa;
            --textcolor: #134e4a;
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
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles (same as index.php) */
        .menu {
            width: 280px;
            background: linear-gradient(135deg, var(--primarycolor) 0%, var(--primarydark) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .menu-container { width: 100%; }
        .profile-container { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.2); margin-bottom: 10px; }
        .profile-title { font-size: 18px; font-weight: 600; margin-bottom: 5px; }
        .profile-subtitle { font-size: 13px; opacity: 0.8; }
        .logout-btn { width: 100%; padding: 10px; margin-top: 15px; background: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.3); border-radius: 5px; cursor: pointer; transition: all 0.3s; }
        .logout-btn:hover { background: rgba(255,255,255,0.3); }
        .menu-row { margin: 5px 0; }
        .menu-btn { padding: 15px 20px; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 15px; }
        .menu-btn:hover { background: rgba(255,255,255,0.1); }
        .menu-btn.menu-active { background: rgba(255,255,255,0.2); border-left: 4px solid white; }
        .menu-btn::before { content: ''; width: 24px; height: 24px; background-size: contain; background-repeat: no-repeat; background-position: center; filter: brightness(0) invert(1); }
        .menu-icon-dashbord::before { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Crect x='3' y='3' width='7' height='7'/%3E%3Crect x='14' y='3' width='7' height='7'/%3E%3Crect x='14' y='14' width='7' height='7'/%3E%3Crect x='3' y='14' width='7' height='7'/%3E%3C/svg%3E"); }
        .menu-icon-doctor::before { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2'/%3E%3Ccircle cx='9' cy='7' r='4'/%3E%3Cpath d='M22 21v-2a4 4 0 0 0-3-3.87'/%3E%3Cpath d='M16 3.13a4 4 0 0 1 0 7.75'/%3E%3C/svg%3E"); }
        .menu-icon-schedule::before { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cline x1='16' y1='2' x2='16' y2='6'/%3E%3Cline x1='8' y1='2' x2='8' y2='6'/%3E%3Cline x1='3' y1='10' x2='21' y2='10'/%3E%3C/svg%3E"); }
        .menu-icon-appoinment::before { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M9 11l3 3L22 4'/%3E%3Cpath d='M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'/%3E%3C/svg%3E"); }
        .menu-icon-patient::before { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'/%3E%3Ccircle cx='12' cy='7' r='4'/%3E%3C/svg%3E"); }
        .menu-icon-pharmacy::before { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z'/%3E%3Cpath d='M9 12h6'/%3E%3Cpath d='M12 9v6'/%3E%3C/svg%3E"); }
        .menu-icon-billing::before { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cline x1='12' y1='1' x2='12' y2='23'/%3E%3Cpath d='M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'/%3E%3C/svg%3E"); }
        .menu-icon-reports::before { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cline x1='18' y1='20' x2='18' y2='10'/%3E%3Cline x1='12' y1='20' x2='12' y2='4'/%3E%3Cline x1='6' y1='20' x2='6' y2='14'/%3E%3C/svg%3E"); }
        .menu-text { font-size: 15px; color: white; text-decoration: none; }
        .non-style-link-menu { text-decoration: none; color: white; display: block; }

        /* Main Content */
        .dash-body {
            flex: 1;
            margin-left: 280px;
            padding: 30px 50px;
        }

        .nav-bar {
            background: white;
            padding: 20px 40px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Report Filters */
        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-size: 14px;
            font-weight: 600;
            color: var(--primarydark);
            margin-bottom: 8px;
        }

        .filter-group input, .filter-group select {
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-group input:focus, .filter-group select:focus {
            outline: none;
            border-color: var(--primarycolor);
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1);
        }

        .btn-primary {
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        /* Report Tabs */
        .report-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #e2e8f0;
        }

        .tab-btn {
            padding: 12px 24px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
            transition: all 0.3s;
        }

        .tab-btn.active {
            color: var(--primarycolor);
            border-bottom-color: var(--primarycolor);
        }

        .tab-btn:hover {
            color: var(--primarydark);
        }

        /* Report Content */
        .report-content {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            animation: fadeIn 0.5s;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: linear-gradient(135deg, var(--primarycolor) 0%, var(--primarydark) 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .summary-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .summary-label {
            font-size: 13px;
            opacity: 0.9;
        }

        /* Tables */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .report-table th {
            background: var(--primarycolor);
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        .report-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .report-table tr:hover {
            background: #f0fdfa;
        }

        .scroll {
            overflow-y: auto;
            max-height: 500px;
        }

        .scroll::-webkit-scrollbar {
            width: 8px;
        }

        .scroll::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .scroll::-webkit-scrollbar-thumb {
            background: var(--primarycolor);
            border-radius: 4px;
        }

        /* Status badges */
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-expiring { background: #fef3c7; color: #92400e; }
        .status-expired { background: #fecaca; color: #991b1b; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-pending { background: #f0fdfa; color: #115e59; }
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
                                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='50' fill='%23e0f2fe'/%3E%3Cpath d='M50 45c8.284 0 15-6.716 15-15s-6.716-15-15-15-15 6.716-15 15 6.716 15 15 15zm0 7.5c-10 0-30 5-30 15V75h60v-7.5c0-10-20-15-30-15z' fill='%230891b2'/%3E%3C/svg%3E" alt="" width="100%" style="border-radius:50%">
                                </td>
                                <td style="padding:0px;margin:0px;">
                                    <p class="profile-title">Administrator</p>
                                    <p class="profile-subtitle">admin@edoc.com</p>
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
                    <td class="menu-btn menu-icon-dashbord">
                        <a href="index.php" class="non-style-link-menu"><p class="menu-text">Dashboard</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor">
                        <a href="staff.php" class="non-style-link-menu"><p class="menu-text">Staff Management</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor">
                        <a href="doctors.php" class="non-style-link-menu"><p class="menu-text">Doctor Management</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-schedule">
                        <a href="schedule.php" class="non-style-link-menu"><p class="menu-text">Schedule</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appoinment">
                        <a href="appointment.php" class="non-style-link-menu"><p class="menu-text">Appointment</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-patient">
                        <a href="patient.php" class="non-style-link-menu"><p class="menu-text">Patients</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-pharmacy">
                        <a href="pharmacy.php" class="non-style-link-menu"><p class="menu-text">Pharmacy</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-billing">
                        <a href="billing.php" class="non-style-link-menu"><p class="menu-text">Billing</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-reports menu-active">
                        <a href="reports.php" class="non-style-link-menu"><p class="menu-text">Reports</p></a>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Main Content -->
        <div class="dash-body">
            <table border="0" width="100%" style="border-spacing: 0;margin:0;padding:0;">
                <tr>
                    <td colspan="2" class="nav-bar">
                        <div>
                            <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">Analytics & Reports</p>
                            <p style="font-size: 14px;color: #64748b;margin-top:5px;">Generate and view comprehensive clinic reports</p>
                        </div>
                        <div style="text-align: right;">
                            <p style="font-size: 14px;color: #64748b;margin:0;">Report Generated</p>
                            <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;">
                                <?php echo date('M j, Y H:i'); ?>
                            </p>
                        </div>
                    </td>
                </tr>
                
                <!-- Report Filters -->
                <tr>
                    <td colspan="4">
                        <div class="filter-section">
                            <form method="GET" action="reports.php">
                                <div class="filter-grid">
                                    <div class="filter-group">
                                        <label>Report Type</label>
                                        <select name="report" onchange="this.form.submit()">
                                            <option value="financial" <?php echo $reportType=='financial'?'selected':''; ?>>Financial Summary</option>
                                            <option value="expiry" <?php echo $reportType=='expiry'?'selected':''; ?>>Expiry Report</option>
                                            <option value="purchase" <?php echo $reportType=='purchase'?'selected':''; ?>>Purchase Report</option>
                                            <option value="supplier" <?php echo $reportType=='supplier'?'selected':''; ?>>Supplier Report</option>
                                            <option value="sales" <?php echo $reportType=='sales'?'selected':''; ?>>Sales Report</option>
                                            <option value="inventory" <?php echo $reportType=='inventory'?'selected':''; ?>>Inventory Report</option>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label>Start Date</label>
                                        <input type="date" name="start_date" value="<?php echo $startDate; ?>">
                                    </div>
                                    <div class="filter-group">
                                        <label>End Date</label>
                                        <input type="date" name="end_date" value="<?php echo $endDate; ?>">
                                    </div>
                                    <?php if($reportType == 'purchase' || $reportType == 'supplier'): ?>
                                    <div class="filter-group">
                                        <label>Supplier</label>
                                        <select name="supplier_id">
                                            <option value="">All Suppliers</option>
                                            <?php
                                            $suppliers = $database->query("SELECT id, businessname FROM suppliers ORDER BY businessname");
                                            while($row = $suppliers->fetch_assoc()) {
                                                $selected = $supplierId == $row['id'] ? 'selected' : '';
                                                echo "<option value='{$row['id']}' $selected>{$row['businessname']}</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <?php endif; ?>
                                    <div class="filter-group">
                                        <button type="submit" class="btn-primary" style="width: 100%;">Generate Report</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
                
                <!-- Report Content -->
                <tr>
                    <td colspan="4">
                        <div class="report-content">
                            <?php
                            switch($reportType) {
                                case 'expiry':
                                    include 'expiry_report.php';
                                    break;
                                case 'purchase':
                                    include 'purchase_report.php';
                                    break;
                                case 'supplier':
                                    include 'supplier_report.php';
                                    break;
                                case 'sales':
                                    include 'sales_report.php';
                                    break;
                                case 'inventory':
                                    include 'inventory_report.php';
                                    break;
                                default:
                                    include 'financial_report.php';
                                    break;
                            }
                            ?>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>