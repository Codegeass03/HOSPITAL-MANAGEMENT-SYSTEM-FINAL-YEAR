<?php
session_start();

if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

$message = "";

// Handle purchase submission
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'purchase') {
    $company_name = sanitize_input($_POST['company_name']);
    $productcategory = sanitize_input($_POST['productcategory']);
    $product_name = sanitize_input($_POST['product_name']);
    $unit = sanitize_input($_POST['unit']);
    $packing_size = sanitize_input($_POST['packing_size']);
    $quantity = sanitize_input($_POST['quantity']);
    $price = sanitize_input($_POST['price']);
    $selling_price = sanitize_input($_POST['selling_price']);
    $party_name = sanitize_input($_POST['party_name']);
    $purchase_method = sanitize_input($_POST['purchase_method']);
    $expiry_date = sanitize_input($_POST['expiry_date']);
    $purchase_date = date('Y-m-d');
    
    // Insert into purchase_master
    $sql1 = "INSERT INTO purchase_master (company_name, productcategory, product_name, unit, packing_size, quantity, price, party_name, purchase_method, expiry_date, purchase_date, username) 
            VALUES ('$company_name', '$productcategory', '$product_name', '$unit', '$packing_size', '$quantity', '$price', '$party_name', '$purchase_method', '$expiry_date', '$purchase_date', '{$_SESSION['user']}')";
    
    if ($database->query($sql1)) {
        // Check if product exists in stock_master
        $check = $database->query("SELECT * FROM stock_master WHERE product_name='$product_name' AND product_company='$company_name'");
        
        if ($check->num_rows > 0) {
            // Update existing stock
            $sql2 = "UPDATE stock_master SET product_quantity = product_quantity + $quantity, product_selling_price = '$selling_price' 
                    WHERE product_name='$product_name' AND product_company='$company_name'";
        } else {
            // Add new stock item
            $sql2 = "INSERT INTO stock_master (product_company, product_name, product_unit, packing_size, product_quantity, product_selling_price) 
                    VALUES ('$company_name', '$product_name', '$unit', '$packing_size', '$quantity', '$selling_price')";
        }
        
        if ($database->query($sql2)) {
            $message = "<p style='color: green;'>✅ Purchase recorded successfully! Stock updated.</p>";
        } else {
            $message = "<p style='color: red;'>Purchase recorded but error updating stock: " . $database->error . "</p>";
        }
    } else {
        $message = "<p style='color: red;'>Error: " . $database->error . "</p>";
    }
}

// Handle supplier submission
if (isset($_POST["submit_supplier"])) {
    $firstname = sanitize_input($_POST["firstname"]);
    $lastname = sanitize_input($_POST["lastname"]);
    $businessname = sanitize_input($_POST["businessname"]);
    $contact = sanitize_input($_POST["contact"]);
    $address = sanitize_input($_POST["address"]);
    $city = sanitize_input($_POST["city"]);
    
    // Insert into suppliers table
    $insert_sql = "INSERT INTO suppliers (firstname, lastname, businessname, contact, address, city) 
                  VALUES ('$firstname', '$lastname', '$businessname', '$contact', '$address', '$city')";
    
    if ($database->query($insert_sql)) {
        $message = "<p style='color: green;'>✅ Supplier added successfully!</p>";
    } else {
        $message = "<p style='color: red;'>Error adding supplier: " . $database->error . "</p>";
    }
}

// Handle company submission - FIXED: Only insert company_name
if (isset($_POST["submit_company"])) {
    $company_name = sanitize_input($_POST["company_name"]);
    
    // Check if company already exists
    $check_sql = "SELECT id FROM company_name WHERE company_name = '$company_name'";
    $result = $database->query($check_sql);
    
    if ($result->num_rows > 0) {
        $message = "<p style='color: orange;'>⚠️ Company already exists in the database.</p>";
    } else {
        // Insert into company_name table - ONLY company_name column
        $insert_sql = "INSERT INTO company_name (company_name) VALUES ('$company_name')";
        
        if ($database->query($insert_sql)) {
            $message = "<p style='color: green;'>✅ Company added successfully!</p>";
        } else {
            $message = "<p style='color: red;'>Error adding company: " . $database->error . "</p>";
        }
    }
}

// Get suppliers
$suppliers = $database->query("SELECT DISTINCT businessname, productcategory FROM suppliers ORDER BY businessname");

// Get companies
$companies = $database->query("SELECT DISTINCT company_name FROM company_name ORDER BY company_name");

// Get all companies for the companies list
$all_companies = $database->query("SELECT * FROM company_name ORDER BY company_name");

// Get products
$products = $database->query("SELECT DISTINCT product_name, company_name, product_category FROM products ORDER BY product_name");

// Get units
$units = $database->query("SELECT DISTINCT unit FROM unit ORDER BY unit");

// Get recent purchases
$recent_purchases = $database->query("
    SELECT pm.*, s.businessname 
    FROM purchase_master pm
    LEFT JOIN suppliers s ON pm.party_name = s.businessname
    ORDER BY pm.purchase_date DESC, pm.id DESC
    LIMIT 10
");

// Get all suppliers for the supplier list
$all_suppliers = $database->query("SELECT * FROM suppliers ORDER BY businessname");

// Pre-fill product if coming from inventory
$prefill_product = isset($_GET['product']) ? sanitize_input($_GET['product']) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase & Supplier Management - MediAccess</title>
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

        .menu-container {
            width: 100%;
        }

        .profile-container {
            padding: 20px;
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

        .menu-icon-pharmacy::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z'/%3E%3Cpath d='M9 12h6'/%3E%3Cpath d='M12 9v6'/%3E%3C/svg%3E");
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

        /* Form Styles */
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
            border-left: 4px solid var(--primarycolor);
        }

        .form-container h2 {
            color: var(--primarycolor);
            margin-bottom: 20px;
            font-size: 20px;
            font-weight: 700;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--textcolor);
            font-size: 14px;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--primarycolor);
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1);
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(8, 145, 178, 0.2);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }

        th {
            background: var(--primarycolor);
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        tr:hover {
            background: #f0fdfa;
        }

        td {
            font-size: 14px;
            color: #334155;
        }

        .required {
            color: #e74c3c;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            color: #721c24;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .alert-warning {
            background: rgba(255, 193, 7, 0.1);
            color: #856404;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
            flex-wrap: wrap;
        }

        .tab {
            padding: 12px 24px;
            background: transparent;
            border: none;
            color: #64748b;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .tab:hover {
            color: var(--primarycolor);
        }

        .tab.active {
            color: var(--primarycolor);
            border-bottom-color: var(--primarycolor);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .action-btn:hover {
            transform: scale(1.05);
        }

        .edit-btn {
            background: var(--accentcolor);
            color: white;
        }

        .delete-btn {
            background: #dc3545;
            color: white;
        }
        
        .note {
            background: #f8f9fa;
            border-left: 4px solid var(--accentcolor);
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .note strong {
            color: var(--primarycolor);
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
                    <td class="menu-btn menu-icon-pharmacy menu-active">
                        <a href="pharmacy.php" class="non-style-link-menu"><p class="menu-text">Pharmacy</p></a>
                    </td>
                </tr>
            </table>
        </div>

        <div class="dash-body">
            <div class="nav-bar">
                <div>
                    <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">Purchase & Supplier Management</p>
                    <p style="font-size: 14px;color: #64748b;margin-top:5px;">Manage purchases, suppliers and companies</p>
                </div>
                <div style="text-align: right;">
                    <p style="font-size: 14px;color: #64748b;margin:0;">Today's Date</p>
                    <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;"><?php echo date('F j, Y'); ?></p>
                </div>
            </div>

            <?php if($message): ?>
                <div class="alert <?php 
                    if (strpos($message, '✅') !== false) echo 'alert-success';
                    elseif (strpos($message, '⚠️') !== false) echo 'alert-warning';
                    else echo 'alert-error';
                ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="tabs">
                <button class="tab active" onclick="switchTab('purchase')">Record Purchase</button>
                <button class="tab" onclick="switchTab('supplier')">Add Supplier</button>
                <button class="tab" onclick="switchTab('company')">Add Company</button>
                <button class="tab" onclick="switchTab('recent')">Recent Purchases</button>
                <button class="tab" onclick="switchTab('suppliers-list')">Suppliers List</button>
                <button class="tab" onclick="switchTab('companies-list')">Companies List</button>
            </div>

            <!-- Purchase Tab -->
            <div id="purchase" class="tab-content active">
                <div class="form-container">
                    <h2>Record New Purchase</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="purchase">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Product Company: <span class="required">*</span></label>
                                <select name="company_name" id="company_name" required>
                                    <option value="">Select Company</option>
                                    <?php
                                    $companies->data_seek(0);
                                    while($comp = $companies->fetch_assoc()) {
                                        echo "<option value='" . $comp['company_name'] . "'>" . $comp['company_name'] . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Product Category: <span class="required">*</span></label>
                                <select name="productcategory" required>
                                    <option value="">Select Category</option>
                                    <option value="Medications">Medications</option>
                                    <option value="Medical Equipment">Medical Equipment</option>
                                    <option value="Medical Supplies">Medical Supplies</option>
                                    <option value="Office Supplies">Office Supplies</option>
                                    <option value="Laboratory Supplies">Laboratory Supplies</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Product Name: <span class="required">*</span></label>
                                <input type="text" name="product_name" value="<?php echo $prefill_product; ?>" list="products_list" required>
                                <datalist id="products_list">
                                    <?php
                                    $products->data_seek(0);
                                    while($prod = $products->fetch_assoc()) {
                                        echo "<option value='" . $prod['product_name'] . "'>";
                                    }
                                    ?>
                                </datalist>
                            </div>

                            <div class="form-group">
                                <label>Unit: <span class="required">*</span></label>
                                <select name="unit" required>
                                    <option value="">Select Unit</option>
                                    <?php
                                    $units->data_seek(0);
                                    while($unit = $units->fetch_assoc()) {
                                        echo "<option value='" . $unit['unit'] . "'>" . $unit['unit'] . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Packing Size:</label>
                                <input type="text" name="packing_size" placeholder="e.g., 100, 200">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Quantity: <span class="required">*</span></label>
                                <input type="number" name="quantity" min="1" required>
                            </div>

                            <div class="form-group">
                                <label>Purchase Price (per unit): <span class="required">*</span></label>
                                <input type="number" name="price" step="0.01" min="0" required>
                            </div>

                            <div class="form-group">
                                <label>Selling Price (per unit): <span class="required">*</span></label>
                                <input type="number" name="selling_price" step="0.01" min="0" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Supplier: <span class="required">*</span></label>
                                <select name="party_name" required>
                                    <option value="">Select Supplier</option>
                                    <?php
                                    $suppliers->data_seek(0);
                                    while($sup = $suppliers->fetch_assoc()) {
                                        echo "<option value='" . $sup['businessname'] . "'>" . $sup['businessname'] . " (" . $sup['productcategory'] . ")</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Payment Method: <span class="required">*</span></label>
                                <select name="purchase_method" required>
                                    <option value="">Select Method</option>
                                    <option value="cash">Cash</option>
                                    <option value="Mpesa">M-Pesa</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Credit">Credit</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Expiry Date: <span class="required">*</span></label>
                                <input type="date" name="expiry_date" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Record Purchase & Update Stock</button>
                    </form>
                </div>
            </div>

            <!-- Add Supplier Tab -->
            <div id="supplier" class="tab-content">
                <div class="form-container">
                    <h2>Add New Supplier</h2>
                    <form name="form1" method="post" class="form-horizontal">
                        <input type="hidden" name="submit_supplier" value="1">
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name:</label>
                                <input type="text" placeholder="First name" name="firstname" />
                            </div>
                            <div class="form-group">
                                <label>Last Name:</label>
                                <input type="text" placeholder="Last name" name="lastname" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Business Name:</label>
                            <input type="text" placeholder="Business name" name="businessname" />
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Contact</label>
                                <input type="text" placeholder="Enter contact Number" name="contact" />
                            </div>

                            <div class="form-group">
                                <label>City</label>
                                <input type="text" placeholder="Enter city" name="city" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Address</label>
                            <textarea name="address" rows="3"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Save Supplier</button>
                    </form>
                </div>
            </div>

            <!-- Add Company Tab - FIXED: Only company name field -->
            <div id="company" class="tab-content">
                <div class="form-container">
                    <h2>Add New Company</h2>
                    <form name="form2" method="post" class="form-horizontal">
                        <input type="hidden" name="submit_company" value="1">
                        
                        <div class="form-group">
                            <label>Company Name: <span class="required">*</span></label>
                            <input type="text" placeholder="Enter company name" name="company_name" required />
                        </div>

                        <button type="submit" class="btn btn-primary">Save Company</button>
                    </form>
                </div>
            </div>

            <!-- Recent Purchases Tab -->
            <div id="recent" class="tab-content">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Company</th>
                                <th>Category</th>
                                <th>Supplier</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                                <th>Expiry</th>
                                <th>Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($recent_purchases->num_rows > 0) {
                                while($row = $recent_purchases->fetch_assoc()) {
                                    $total = $row['quantity'] * $row['price'];
                                    $expiry_class = strtotime($row['expiry_date']) < strtotime('+6 months') ? 'style="color: #e74c3c;"' : '';
                                    
                                    echo "<tr>";
                                    echo "<td>" . date('M d, Y', strtotime($row['purchase_date'])) . "</td>";
                                    echo "<td><strong>" . $row['product_name'] . "</strong></td>";
                                    echo "<td>" . $row['company_name'] . "</td>";
                                    echo "<td>" . $row['productcategory'] . "</td>";
                                    echo "<td>" . $row['businessname'] . "</td>";
                                    echo "<td>" . $row['quantity'] . " " . $row['unit'] . "</td>";
                                    echo "<td>KES " . number_format($row['price'], 2) . "</td>";
                                    echo "<td>KES " . number_format($total, 2) . "</td>";
                                    echo "<td $expiry_class>" . date('M d, Y', strtotime($row['expiry_date'])) . "</td>";
                                    echo "<td>" . $row['purchase_method'] . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='10' style='text-align: center;'>No purchases recorded yet</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Suppliers List Tab -->
            <div id="suppliers-list" class="tab-content">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>First name</th>
                                <th>Last name</th>
                                <th>Business Name</th>
                                <th>Contact</th>
                                <th>Address</th>
                                <th>City</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($all_suppliers->num_rows > 0) {
                                while($row = $all_suppliers->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . $row['firstname'] . "</td>";
                                    echo "<td>" . $row['lastname'] . "</td>";
                                    echo "<td>" . $row['businessname'] . "</td>";
                                    echo "<td>" . $row['contact'] . "</td>";
                                    echo "<td>" . $row['address'] . "</td>";
                                    echo "<td>" . $row['city'] . "</td>";
                                    echo "<td>
                                        <div class='action-buttons'>
                                            <button class='action-btn edit-btn'>Edit</button>
                                            <button class='action-btn delete-btn'>Delete</button>
                                        </div>
                                    </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='7' style='text-align: center;'>No suppliers found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Companies List Tab - FIXED: Only show company name -->
            <div id="companies-list" class="tab-content">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Company Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($all_companies->num_rows > 0) {
                                while($row = $all_companies->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . $row['company_name'] . "</td>";
                                    echo "<td>
                                        <div class='action-buttons'>
                                            <button class='action-btn edit-btn'>Edit</button>
                                            <button class='action-btn delete-btn'>Delete</button>
                                        </div>
                                    </td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='2' style='text-align: center;'>No companies found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            const tabButtons = document.querySelectorAll('.tab');
            tabButtons.forEach(btn => btn.classList.remove('active'));
            
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        // Auto-fill company when product is selected
        document.querySelector('input[name="product_name"]').addEventListener('change', function() {
            const productName = this.value;
            // You can add AJAX call here to fetch product details if needed
        });
    </script>
</body>
</html>