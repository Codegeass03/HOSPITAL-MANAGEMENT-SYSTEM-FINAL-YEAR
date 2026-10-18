<?php
session_start();

if(isset($_SESSION["user"])){
    if(($_SESSION["user"])=="" or $_SESSION['usertype']!='a'){
        header("location: ../login.php");
    }
}else{
    header("location: ../login.php");
}

include("../connection.php");

// Get bill ID from URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($id == 0) {
    header("location: pharmacy.php");
    exit();
}

// Get bill header information
$bill_header_query = $database->prepare("
    SELECT b.*, p.pname, p.ptel, p.pemail, a.appodate,
           COALESCE(bp.receipt_no, 'N/A') as receipt_no,
           COALESCE(bp.payment_method, 'N/A') as payment_method,
           COALESCE(bp.payment_date, b.date) as payment_date,
           COALESCE(bp.status, 'pending') as payment_status
    FROM billing b
    LEFT JOIN patient p ON b.pid = p.pid
    LEFT JOIN appointment a ON b.appoid = a.appoid
    LEFT JOIN billing_payments bp ON b.id = bp.reference_id AND bp.type = 'pharmacy'
    WHERE b.id = ?
");
$bill_header_query->bind_param("i", $id);
$bill_header_query->execute();
$bill_header_result = $bill_header_query->get_result();

if($bill_header_result->num_rows == 0) {
    header("location: pharmacy.php");
    exit();
}

$bill_header = $bill_header_result->fetch_assoc();

// Get bill details
$bill_details_query = $database->prepare("
    SELECT bd.*, sm.product_unit, sm.packing_size
    FROM billing_details bd
    LEFT JOIN stock_master sm ON bd.product_name = sm.product_name AND bd.product_company = sm.product_company
    WHERE bd.billing_id = ?
");
$bill_details_query->bind_param("i", $id);
$bill_details_query->execute();
$bill_details_result = $bill_details_query->get_result();

$total_amount = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill Details - Smart Clinic Management System</title>
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

        /* Sidebar Styles - Identical to other files */
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

        .menu-icon-pills::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z'/%3E%3Cpath d='M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z'/%3E%3Cpath d='M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0'/%3E%3Cpath d='M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5'/%3E%3C/svg%3E");
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

        /* Bill Details Container */
        .bill-details-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .bill-header {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }

        .bill-info-item {
            display: flex;
            flex-direction: column;
        }

        .bill-info-label {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .bill-info-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--textcolor);
        }

        /* Tables */
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow-x: auto;
            margin-bottom: 30px;
        }

        .table-container table {
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
        }

        tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }

        tbody tr:hover {
            background: #f0fdfa;
        }

        td {
            padding: 12px 15px;
            font-size: 14px;
            color: #334155;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pending {
            background: rgba(251, 191, 36, 0.1);
            color: #92400e;
        }

        .badge-paid {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
        }

        /* Buttons */
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(8, 145, 178, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, var(--accentcolor) 0%, var(--primarylight) 100%);
            color: white;
        }

        /* Total Section */
        .total-section {
            background: #f0fdfa;
            border: 2px solid var(--accentcolor);
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            text-align: right;
        }

        .total-amount {
            font-size: 24px;
            font-weight: 700;
            color: var(--primarydark);
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .dash-body {
                padding: 20px 30px;
            }
        }

        @media (max-width: 992px) {
            .dash-body {
                margin-left: 0;
                padding: 20px;
            }
            .menu {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            .menu.active {
                transform: translateX(0);
            }
        }

        @media (max-width: 768px) {
            .nav-bar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .bill-header {
                grid-template-columns: 1fr;
            }
            .table-container {
                padding: 10px;
            }
            .table-container table {
                min-width: 800px;
            }
        }

        @media (max-width: 576px) {
            .dash-body {
                padding: 15px;
            }
            .action-buttons {
                flex-direction: column;
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
                                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ccircle cx='50' cy='50' r='50' fill='%23e0f2fe'/%3E%3Cpath d='M50 45c8.284 0 15-6.716 15-15s-6.716-15-15-15-15 6.716-15 15 6.716 15 15 15zm0 7.5c-10 0-30 5-30 15V75h60v-7.5c0-10-20-15-30-15z' fill='%230891b2'/%3E%3C/svg%3E" alt="" width="100%" style="border-radius:50%">
                                </td>
                                <td style="padding:0px;margin:0px;">
                                    <p class="profile-title">Administrator</p>
                                    <p class="profile-subtitle"><?php echo htmlspecialchars($_SESSION['user']); ?></p>
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
                    <td class="menu-btn menu-icon-pills">
                        <a href="pharmacy.php" class="non-style-link-menu"><p class="menu-text">Pharmacy</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-billing menu-active">
                        <a href="billing.php" class="non-style-link-menu"><p class="menu-text">Billing</p></a>
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
                    <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">Pharmacy Bill Details</p>
                    <p style="font-size: 14px;color: #64748b;margin-top:5px;">Detailed view of pharmacy bill and items</p>
                </div>
                <div style="text-align: right;">
                    <p style="font-size: 14px;color: #64748b;margin:0;">Today's Date</p>
                    <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;"><?php echo date('F j, Y'); ?></p>
                </div>
            </div>

            <!-- Bill Details Container -->
            <div class="bill-details-container">
                <!-- Bill Header Information -->
                <div class="bill-header">
                    <div class="bill-info-item">
                        <span class="bill-info-label">Bill No:</span>
                        <span class="bill-info-value"><?php echo htmlspecialchars($bill_header['bill_no']); ?></span>
                    </div>
                    <div class="bill-info-item">
                        <span class="bill-info-label">Patient Name:</span>
                        <span class="bill-info-value"><?php echo htmlspecialchars($bill_header['pname']); ?></span>
                    </div>
                    <div class="bill-info-item">
                        <span class="bill-info-label">Contact:</span>
                        <span class="bill-info-value"><?php echo htmlspecialchars($bill_header['ptel']); ?></span>
                    </div>
                    <div class="bill-info-item">
                        <span class="bill-info-label">Bill Date:</span>
                        <span class="bill-info-value"><?php echo date('M d, Y', strtotime($bill_header['date'])); ?></span>
                    </div>
                    <div class="bill-info-item">
                        <span class="bill-info-label">Bill Type:</span>
                        <span class="bill-info-value"><?php echo htmlspecialchars($bill_header['bill_type']); ?></span>
                    </div>
                    <div class="bill-info-item">
                        <span class="bill-info-label">Payment Status:</span>
                        <span class="bill-info-value">
                            <span class="badge <?php echo $bill_header['payment_status'] == 'paid' ? 'badge-paid' : 'badge-pending'; ?>">
                                <?php echo ucfirst($bill_header['payment_status']); ?>
                            </span>
                        </span>
                    </div>
                    <?php if($bill_header['receipt_no'] != 'N/A'): ?>
                    <div class="bill-info-item">
                        <span class="bill-info-label">Receipt No:</span>
                        <span class="bill-info-value"><?php echo htmlspecialchars($bill_header['receipt_no']); ?></span>
                    </div>
                    <div class="bill-info-item">
                        <span class="bill-info-label">Payment Method:</span>
                        <span class="bill-info-value"><?php echo htmlspecialchars($bill_header['payment_method']); ?></span>
                    </div>
                    <div class="bill-info-item">
                        <span class="bill-info-label">Payment Date:</span>
                        <span class="bill-info-value"><?php echo date('M d, Y', strtotime($bill_header['payment_date'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Bill Items Table -->
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Product Company</th>
                                <th>Product Name</th>
                                <th>Unit</th>
                                <th>Pack Size</th>
                                <th>Price (KSh)</th>
                                <th>Quantity</th>
                                <th>Total (KSh)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if($bill_details_result->num_rows > 0) {
                                while($row = $bill_details_result->fetch_assoc()) {
                                    $item_total = $row['price'] * $row['quantity'];
                                    $total_amount += $item_total;
                                    
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['product_company']) . "</td>";
                                    echo "<td><strong>" . htmlspecialchars($row['product_name']) . "</strong></td>";
                                    echo "<td>" . htmlspecialchars($row['product_unit'] ?? 'N/A') . "</td>";
                                    echo "<td>" . htmlspecialchars($row['packing_size'] ?? 'N/A') . "</td>";
                                    echo "<td>" . number_format($row['price'], 2) . "</td>";
                                    echo "<td>" . $row['quantity'] . "</td>";
                                    echo "<td><strong>" . number_format($item_total, 2) . "</strong></td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' style='text-align:center;padding:30px;'>No items found in this bill</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

                <!-- Total Amount -->
                <div class="total-section">
                    <div class="total-amount">
                        Grand Total: KSh <?php echo number_format($total_amount, 2); ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button class="btn btn-info" onclick="window.print()">
                        <svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                            <polyline points='6 9 6 2 18 2 18 9'></polyline>
                            <path d='M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2'></path>
                            <rect x='6' y='14' width='12' height='8'></rect>
                        </svg>
                        Print Bill
                    </button>
                    <a href="pharmacy.php" class="btn btn-primary">
                        <svg width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                            <path d='M19 12H5M12 19l-7-7 7-7'></path>
                        </svg>
                        Back to Pharmacy
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Print functionality
        function printBill() {
            window.print();
        }

        // Add any additional JavaScript functionality here if needed
    </script>
</body>
</html>