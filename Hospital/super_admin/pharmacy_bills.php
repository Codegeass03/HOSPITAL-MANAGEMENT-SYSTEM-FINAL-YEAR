<?php
session_start();

if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

// Function to get total bill amount
function get_total($bill_id, $database) {
    $total = 0;
    $res2 = $database->query("SELECT * FROM billing_details WHERE billing_id = $bill_id");
    while($row = $res2->fetch_assoc()) {
        $total = $total + ($row["price"] * $row["quantity"]);
    }
    return $total;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Bills - Smart Clinic Management System</title>
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

        /* Sidebar Styles */
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

        .menu::-webkit-scrollbar {
            width: 6px;
        }

        .menu::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }

        .menu::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }

        .menu::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }

        .menu-container {
            width: 100%;
        }

        .profile-container {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 10px;
        }

        .profile-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .profile-subtitle {
            font-size: 13px;
            opacity: 0.8;
        }

        .logout-btn {
            width: 100%;
            padding: 10px;
            margin-top: 15px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
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
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .menu-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        .menu-btn.menu-active {
            background: rgba(255,255,255,0.2);
            border-left: 4px solid white;
        }

        .menu-btn::before {
            content: '';
            width: 24px;
            height: 24px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            filter: brightness(0) invert(1);
        }

        .menu-icon-dashbord::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Crect x='3' y='3' width='7' height='7'/%3E%3Crect x='14' y='3' width='7' height='7'/%3E%3Crect x='14' y='14' width='7' height='7'/%3E%3Crect x='3' y='14' width='7' height='7'/%3E%3C/svg%3E");
        }

        .menu-icon-doctor::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2'/%3E%3Ccircle cx='9' cy='7' r='4'/%3E%3Cpath d='M22 21v-2a4 4 0 0 0-3-3.87'/%3E%3Cpath d='M16 3.13a4 4 0 0 1 0 7.75'/%3E%3C/svg%3E");
        }

        .menu-icon-schedule::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cline x1='16' y1='2' x2='16' y2='6'/%3E%3Cline x1='8' y1='2' x2='8' y2='6'/%3E%3Cline x1='3' y1='10' x2='21' y2='10'/%3E%3C/svg%3E");
        }

        .menu-icon-appoinment::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M9 11l3 3L22 4'/%3E%3Cpath d='M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'/%3E%3C/svg%3E");
        }

        .menu-icon-patient::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'/%3E%3Ccircle cx='12' cy='7' r='4'/%3E%3C/svg%3E");
        }

        .menu-icon-pharmacy::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z'/%3E%3Cpath d='M9 12h6'/%3E%3Cpath d='M12 9v6'/%3E%3C/svg%3E");
        }

        .menu-icon-billing::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cline x1='12' y1='1' x2='12' y2='23'/%3E%3Cpath d='M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'/%3E%3C/svg%3E");
        }

        .menu-icon-reports::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cline x1='18' y1='20' x2='18' y2='10'/%3E%3Cline x1='12' y1='20' x2='12' y2='4'/%3E%3Cline x1='6' y1='20' x2='6' y2='14'/%3E%3C/svg%3E");
        }

        .menu-text {
            font-size: 15px;
            color: white;
            text-decoration: none;
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

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
            border-left: 4px solid var(--primarycolor);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(8, 145, 178, 0.2);
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--primarycolor);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }

        /* Section Titles */
        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--primarycolor);
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--accentcolor);
        }

        /* Filter Section */
        .filter-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: auto 1fr auto 1fr auto;
            gap: 15px;
            align-items: center;
        }

        .filter-label {
            font-weight: 600;
            color: var(--primarydark);
            font-size: 14px;
        }

        .input-text, .box {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .input-text:focus, .box:focus {
            outline: none;
            border-color: var(--primarycolor);
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primarycolor);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primarydark);
            transform: translateY(-2px);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        /* Tables */
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--primarycolor);
            color: white;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            border-right: 1px solid rgba(255,255,255,0.2);
        }

        th:last-child {
            border-right: none;
        }

        tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }

        tbody tr:hover {
            background: #f0fdfa;
        }

        td {
            padding: 15px;
            font-size: 14px;
            color: #334155;
            border-right: 1px solid #f1f5f9;
        }

        td:last-child {
            border-right: none;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }

        .badge-success {
            background: #10b981;
        }

        .badge-warning {
            background: #f59e0b;
        }

        .badge-danger {
            background: #ef4444;
        }

        /* Action Links */
        .action-link {
            color: var(--primarycolor);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .action-link:hover {
            color: var(--primarydark);
            text-decoration: underline;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .content {
            padding: 20px 0;
            text-align: center;
        }

        @keyframes transitionIn-Y-bottom {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .table-container {
            animation: transitionIn-Y-bottom 0.5s;
        }

        .stats-grid {
            animation: transitionIn-Y-bottom 0.5s;
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
                    <td class="menu-btn menu-icon-billing menu-active">
                        <a href="pharmacy_bills.php" class="non-style-link-menu"><p class="menu-text">Pharmacy Bills</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-reports">
                        <a href="reports.php" class="non-style-link-menu"><p class="menu-text">Reports</p></a>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Main Content -->
        <div class="dash-body">
            <div class="nav-bar">
                <div>
                    <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">Pharmacy Bills</p>
                    <p style="font-size: 14px;color: #64748b;margin-top:5px;">View and manage pharmacy billing records</p>
                </div>
                <div style="text-align: right;">
                    <p style="font-size: 14px;color: #64748b;margin:0;">Today's Date</p>
                    <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;"><?php echo date('F j, Y'); ?></p>
                </div>
            </div>

            <!-- Statistics Overview -->
            <?php
            // Calculate statistics - Show ALL bills from billing table
            $total_bills = $database->query("SELECT COUNT(*) as count FROM billing")->fetch_assoc()['count'];
            $total_revenue = 0;
            $paid_bills = $database->query("SELECT COUNT(*) as count FROM billing WHERE payment_status = 'paid'")->fetch_assoc()['count'];
            
            // Calculate total revenue from ALL bills
            $bills_result = $database->query("SELECT id FROM billing");
            while($bill = $bills_result->fetch_assoc()) {
                $total_revenue += get_total($bill['id'], $database);
            }
            ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_bills; ?></div>
                    <div class="stat-label">Total Pharmacy Bills</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $paid_bills; ?></div>
                    <div class="stat-label">Paid Bills</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">KES <?php echo number_format($total_revenue, 2); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-container">
                <form action="" name="form1" method="post">
                    <div class="filter-row">
                        <label class="filter-label">Start Date:</label>
                        <input type="date" name="dt" id="dt" class="input-text" required>
                        
                        <label class="filter-label">End Date:</label>
                        <input type="date" name="dt2" id="dt2" class="input-text" required>
                        
                        <input type="submit" name="submit1" value="Filter Bills" class="btn btn-primary">
                        <button type="button" name="submit2" class="btn btn-warning" onclick="window.location.href=window.location.href">
                            Clear Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Bills Table Section -->
            <h2 class="section-title">Pharmacy Bills</h2>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Bill No</th>
                            <th>Generated By</th>
                            <th>Customer Name</th>
                            <th>Bill Type</th>
                            <th>Bill Date</th>
                            <th>Total Amount</th>
                            <th>Payment Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if(isset($_POST["submit1"])) {
                            // Show ALL bills filtered by date
                            $res = $database->query("SELECT * FROM billing WHERE (date >= '$_POST[dt]' AND date <= '$_POST[dt2]') ORDER BY id DESC");
                        } else {
                            // Show ALL bills without filtering by bill_type
                            $res = $database->query("SELECT * FROM billing ORDER BY id DESC");
                        }

                        if ($res->num_rows > 0) {
                            while($row = $res->fetch_assoc()) {
                                $total_amount = get_total($row["id"], $database);
                                echo "<tr>";
                                echo "<td><strong>" . htmlspecialchars($row["bill_no"]) . "</strong></td>";
                                echo "<td>" . htmlspecialchars($row["username"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["CustomerName"]) . "</td>";
                                echo "<td>" . htmlspecialchars($row["bill_type"]) . "</td>";
                                echo "<td>" . date('M d, Y', strtotime($row["date"])) . "</td>";
                                echo "<td><strong>KES " . number_format($total_amount, 2) . "</strong></td>";
                                echo "<td>";
                                if($row["payment_status"] == 'paid') {
                                    echo "<span class='badge badge-success'>Paid</span>";
                                } else {
                                    echo "<span class='badge badge-warning'>Pending</span>";
                                }
                                echo "</td>";
                                echo "<td>
                                        <a href='view_bill_details.php?id=" . $row["id"] . "' class='action-link'>
                                            View Details
                                        </a>
                                      </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo '<tr>
                                <td colspan="8">
                                    <div class="empty-state">
                                        <p style="font-size:20px;color:#64748b;">No pharmacy bills found</p>
                                        <p style="color:#64748b;margin-top:10px;">' . (isset($_POST["submit1"]) ? "Try adjusting your date filter" : "No bills have been generated yet") . '</p>
                                        <a href="pharmacy.php" style="text-decoration:none;">
                                            <button class="btn btn-primary" style="margin-top:20px;">Go to Pharmacy</button>
                                        </a>
                                    </div>
                                </td>
                            </tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Set default dates for better UX
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const oneWeekAgo = new Date(today);
            oneWeekAgo.setDate(today.getDate() - 7);
            
            document.getElementById('dt').value = oneWeekAgo.toISOString().split('T')[0];
            document.getElementById('dt2').value = today.toISOString().split('T')[0];
        });
    </script>
</body>
</html>