<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Pharmacy Management System</title>
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

        .menu-icon-prescriptions::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M9 11l3 3L22 4'/%3E%3Cpath d='M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11'/%3E%3C/svg%3E");
        }

        .menu-icon-inventory::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z'/%3E%3Cpath d='M9 12h6'/%3E%3Cpath d='M12 9v6'/%3E%3C/svg%3E");
        }

        .menu-icon-sales::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cline x1='12' y1='1' x2='12' y2='23'/%3E%3Cpath d='M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'/%3E%3C/svg%3E");
        }

        .menu-icon-suppliers::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2'/%3E%3Ccircle cx='9' cy='7' r='4'/%3E%3Cpath d='M22 21v-2a4 4 0 0 0-3-3.87'/%3E%3Cpath d='M16 3.13a4 4 0 0 1 0 7.75'/%3E%3C/svg%3E");
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

        /* Quick Actions */
        .quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 30px;
        }

        .action-btn {
            padding: 12px 20px;
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(8, 145, 178, 0.2);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        /* Module Cards */
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .module-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-top: 3px solid var(--primarycolor);
        }

        .module-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 20px rgba(8, 145, 178, 0.15);
        }

        .module-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .module-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primarydark);
            margin-bottom: 12px;
        }

        .module-description {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .module-link {
            display: inline-block;
            padding: 10px 20px;
            background: var(--primarycolor);
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .module-link:hover {
            background: var(--primarydark);
            transform: scale(1.05);
        }

        /* Tables */
        .dashbord-tables {
            width: 100%;
            margin-top: 30px;
        }

        .sub-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .table-headin {
            background: var(--primarycolor);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        .sub-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.2s;
        }

        .sub-table tbody tr:hover {
            background: #f0fdfa;
        }

        .sub-table tbody td {
            padding: 12px 15px;
            font-size: 14px;
            color: #334155;
        }

        .scroll {
            overflow-y: auto;
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

        @keyframes transitionIn-Y-over {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
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

        .dashbord-tables {
            animation: transitionIn-Y-over 0.5s;
        }

        .stats-grid {
            animation: transitionIn-Y-bottom 0.5s;
        }

        .modules-grid {
            animation: transitionIn-Y-bottom 0.6s;
        }
    </style>
</head>
<body>
    <?php
    //import database
    include("../connection.php");

    // Get counts for pharmacy dashboard stats - using actual tables from your database
    $pendingPrescriptions = $database->query("SELECT COUNT(*) as count FROM ehr_prescriptions WHERE status='pending_pharmacy'")->fetch_assoc()['count'];
    $todaySales = $database->query("SELECT COUNT(*) as count FROM billing WHERE DATE(date) = CURDATE()")->fetch_assoc()['count'];
    $totalProducts = $database->query("SELECT COUNT(*) as count FROM stock_master")->fetch_assoc()['count'];
    $lowStockCount = $database->query("SELECT COUNT(*) as count FROM stock_master WHERE product_quantity < 20")->fetch_assoc()['count'];
    $pendingBills = $database->query("SELECT COUNT(*) as count FROM billing WHERE payment_status='pending'")->fetch_assoc()['count'];
    $totalSuppliers = $database->query("SELECT COUNT(*) as count FROM suppliers")->fetch_assoc()['count'];
    
    // Get today's date
    date_default_timezone_set('Africa/Nairobi');
    $today = date('Y-m-d');
    ?>
    
   <div class="container">
        <!-- Sidebar Menu - Enhanced with proper icons -->
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
                                    <p class="profile-title">Pharmacist</p>
                                    <p class="profile-subtitle">pharmacy@smartclinic.com</p>
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
                    <td class="menu-btn menu-icon-dashbord menu-active">
                        <a href="index.php" class="non-style-link-menu"><p class="menu-text">Dashboard</p></a>
                    </td>
                </tr>
               
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-pharmacy">
                        <a href="pharmacy.php" class="non-style-link-menu"><p class="menu-text">Pharmacy</p></a>
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
            <table border="0" width="100%" style="border-spacing: 0;margin:0;padding:0;">
                <tr>
                    <td colspan="2" class="nav-bar">
                        <div>
                            <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">Pharmacy Dashboard</p>
                            <p style="font-size: 14px;color: #64748b;margin-top:5px;">Welcome back, Pharmacist</p>
                        </div>
                        <div style="text-align: right;">
                            <p style="font-size: 14px;color: #64748b;margin:0;">Today's Date</p>
                            <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;">
                                <?php echo date('F j, Y'); ?>
                            </p>
                        </div>
                    </td>
                </tr>
                
                <!-- Statistics Overview -->
                <tr>
                    <td colspan="4">
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $pendingPrescriptions; ?></div>
                                <div class="stat-label">Pending Prescriptions</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $todaySales; ?></div>
                                <div class="stat-label">Today's Sales</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $lowStockCount; ?></div>
                                <div class="stat-label">Low Stock Items</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $pendingBills; ?></div>
                                <div class="stat-label">Pending Bills</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $totalProducts; ?></div>
                                <div class="stat-label">Total Products</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $totalSuppliers; ?></div>
                                <div class="stat-label">Suppliers</div>
                            </div>
                        </div>
                    </td>
                </tr>
                
                <!-- Quick Actions -->
                <tr>
                    <td colspan="4">
                        <p class="section-title">Quick Actions</p>
                        <div class="quick-actions">
                            <a href="pharmacy.php?action=process" class="action-btn">Process Prescription</a>
                            <!-- <a href="billing.php?action=add" class="action-btn">Record Sale</a> -->
                            <a href="pharmacy_bills.php?action=add" class="action-btn">View Bills</a>
                            <a href="pharmacy_purchase.php?action=add" class="action-btn">Place Order</a>
                            <a href="pharmacy_inventory.php" class="action-btn">Check Stock</a>
                            <a href="reports.php" class="action-btn">View Reports</a>
                        </div>
                    </td>
                </tr>
                
                <!-- System Modules -->
                <tr>
                    <td colspan="4">
                        <p class="section-title">Pharmacy Modules</p>
                        <div class="modules-grid">
                            <div class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Prescription Management</div>
                                <div class="module-description">
                                    Process and manage patient prescriptions  and dispense medications.
                                </div>
                                <a href="pharmacy.php" class="module-link">Manage Prescriptions</a>
                            </div>
                            
                            <div class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Stock Management</div>
                                <div class="module-description">
                                    Track medication stock levels, manage inventory, and set up automatic reorder alerts.
                                </div>
                                <a href="pharmacy_inventory.php" class="module-link">Manage Stock</a>
                            </div>
                            
                            <div class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Sales & Billing</div>
                                <div class="module-description">
                                    Process medication sales, generate bills and track payment transactions.
                                </div>
                                <a href="pharmacy_bills.php" class="module-link">Manage Bills</a>
                            </div>
                            
                            <div class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Supplier Management</div>
                                <div class="module-description">
                                    Manage supplier information.
                                </div>
                                <a href="pharmacy_suppliers.php" class="module-link">Manage Suppliers</a>
                            </div>
                            
                            <div class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Purchase Orders</div>
                                <div class="module-description">
                                    Create and track medication orders, manage purchase requests, and monitor deliveries.
                                </div>
                                <a href="pharmacy_purchase.php" class="module-link">Purchase Orders</a>
                            </div>
                            
                            <div class="module-card">
                                <div class="module-icon"></div>
                                <div class="module-title">Reports & Analytics</div>
                                <div class="module-description">
                                    Generate pharmacy reports on sales, inventory, prescriptions, and financial performance.
                                </div>
                                <a href="reports.php" class="module-link">View Reports</a>
                            </div>
                        </div>
                    </td>
                </tr>
                
                <!-- Recent Activity -->
                <tr>
                    <td colspan="4">
                        <p class="section-title">Recent Activity</p>
                        <table width="100%" border="0" class="dashbord-tables" style="table-layout: fixed;">
                            <tr>
                                <td width="50%" style="padding-right: 15px; vertical-align: top;">
                                    <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                        <p style="font-size:20px;font-weight:600;color:var(--primarycolor);margin-bottom:15px;">
                                            Pending Prescriptions
                                        </p>
                                        <div class="scroll" style="height: 300px;">
                                            <table width="100%" class="sub-table" border="0">
                                                <thead>
                                                    <tr>
                                                        <th class="table-headin">Prescription #</th>
                                                        <th class="table-headin">Patient</th>
                                                        <th class="table-headin">Medication</th>
                                                        <th class="table-headin">Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $sqlmain= "SELECT ep.id, ep.medication, p.pname, ep.created_at 
                                                              FROM ehr_prescriptions ep 
                                                              JOIN appointment a ON ep.appoid = a.appoid 
                                                              JOIN patient p ON a.pid = p.pid 
                                                              WHERE ep.status = 'pending_pharmacy' 
                                                              ORDER BY ep.created_at DESC 
                                                              LIMIT 7";
                                                    $result= $database->query($sqlmain);
                                                    
                                                    if($result->num_rows==0){
                                                        echo '<tr><td colspan="4" style="text-align:center;padding:20px;">No pending prescriptions.</td></tr>';
                                                    } else {
                                                        while($row=$result->fetch_assoc()){
                                                            echo '<tr>
                                                                <td>'.$row["id"].'</td>
                                                                <td>'.substr($row["pname"],0,20).'</td>
                                                                <td>'.substr($row["medication"],0,20).'</td>
                                                                <td>'.substr($row["created_at"],0,10).'</td>
                                                            </tr>';
                                                        }
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                                <td width="50%" style="padding-left: 15px; vertical-align: top;">
                                    <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                        <p style="font-size:20px;font-weight:600;color:var(--primarycolor);margin-bottom:15px;">
                                            Low Stock Items
                                        </p>
                                        <div class="scroll" style="height: 300px;">
                                            <table width="100%" class="sub-table" border="0">
                                                <thead>
                                                    <tr>
                                                        <th class="table-headin">Product</th>
                                                        <th class="table-headin">Current Stock</th>
                                                        <th class="table-headin">Price</th>
                                                        <th class="table-headin">Supplier</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    $sqlmain= "SELECT sm.product_name, sm.product_quantity, sm.product_selling_price, sm.product_company 
                                                              FROM stock_master sm 
                                                              WHERE sm.product_quantity < 20 
                                                              ORDER BY sm.product_quantity ASC 
                                                              LIMIT 7";
                                                    $result= $database->query($sqlmain);
                                                    
                                                    if($result->num_rows==0){
                                                        echo '<tr><td colspan="4" style="text-align:center;padding:20px;">No low stock items.</td></tr>';
                                                    } else {
                                                        while($row=$result->fetch_assoc()){
                                                            echo '<tr>
                                                                <td>'.substr($row["product_name"],0,20).'</td>
                                                                <td>'.$row["product_quantity"].'</td>
                                                                <td>KSh '.number_format($row["product_selling_price"],2).'</td>
                                                                <td>'.substr($row["product_company"],0,15).'</td>
                                                            </tr>';
                                                        }
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>