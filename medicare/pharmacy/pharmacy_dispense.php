<?php
session_start();


include("../connection.php");

// NEW: Check stock availability function
function check_stock_availability($company_name, $product_name, $unit, $packing_size, $quantity, $database) {
    $sql = "SELECT product_quantity FROM stock_master 
            WHERE product_company = ? 
            AND product_name = ? 
            AND product_unit = ? 
            AND packing_size = ?";
    
    $stmt = $database->prepare($sql);
    if (!$stmt) {
        return "Database error: " . $database->error;
    }
    
    $stmt->bind_param("ssss", $company_name, $product_name, $unit, $packing_size);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $available_quantity = $row['product_quantity'];
        if ($available_quantity >= $quantity) {
            return true;
        } else {
            return "Insufficient stock for {$product_name} ({$unit} - {$packing_size}). Available: {$available_quantity}, Requested: {$quantity}";
        }
    } else {
        return "Product not found in stock: {$product_name} ({$unit} - {$packing_size})";
    }
}

// NEW: Deduct stock function
function deduct_stock($company_name, $product_name, $unit, $packing_size, $quantity, $database) {
    $update_stock = "UPDATE stock_master 
                    SET product_quantity = product_quantity - ? 
                    WHERE product_company = ? AND product_name = ? AND product_unit = ? AND packing_size = ?";
    $stmt = $database->prepare($update_stock);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("isssi", $quantity, $company_name, $product_name, $unit, $packing_size);
    return $stmt->execute();
}

function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

$message = "";
$username = $_SESSION['username'] ?? 'Admin';

// Initialize cart if not exists
if (!isset($_SESSION['pharmacy_cart'])) {
    $_SESSION['pharmacy_cart'] = array();
}

// Get patient and prescription data
$patient_data = [];
$prescriptions_data = [];
$from_patient = false;
$patient_id = null;
$appoid = null;

// Check if we're coming with a patient ID
if (isset($_GET['patient_id']) && !empty($_GET['patient_id'])) {
    $patient_id = sanitize_input($_GET['patient_id']);
    $from_patient = true;
    
    // Fetch patient details
    $patient_sql = "SELECT pid, pname, ptel, pemail, paddress FROM patient WHERE pid = ?";
    $stmt = $database->prepare($patient_sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $patient_result = $stmt->get_result();
    
    if ($patient_result->num_rows > 0) {
        $patient_data = $patient_result->fetch_assoc();
        
        // Get the latest completed appointment for this patient
        $appointment_sql = "SELECT appoid FROM appointment 
                           WHERE pid = ? AND status = 'Complete' 
                           AND workflow_stage IN ('consultation_complete', 'prescription_ready')
                           ORDER BY appodate DESC LIMIT 1";
        $stmt_app = $database->prepare($appointment_sql);
        $stmt_app->bind_param("i", $patient_id);
        $stmt_app->execute();
        $app_result = $stmt_app->get_result();
        
        if ($app_result->num_rows > 0) {
            $app_data = $app_result->fetch_assoc();
            $appoid = $app_data['appoid'];
        }
        
        // Fetch E-prescriptions for this patient's appointment
        if ($appoid) {
            $ehr_prescriptions_sql = "
                SELECT ep.*, d.docname, a.appodate, 'ehr' as prescription_type
                FROM ehr_prescriptions ep
                INNER JOIN appointment a ON ep.appoid = a.appoid
                INNER JOIN schedule s ON a.scheduleid = s.scheduleid
                INNER JOIN doctor d ON s.docid = d.docid
                WHERE a.pid = ? AND a.appoid = ?
                AND ep.status IN ('pending_pharmacy', 'dispensed')
                ORDER BY ep.created_at DESC
            ";
            
            $stmt_ehr = $database->prepare($ehr_prescriptions_sql);
            $stmt_ehr->bind_param("ii", $patient_id, $appoid);
            $stmt_ehr->execute();
            $ehr_result = $stmt_ehr->get_result();
            
            while ($ehr_prescription = $ehr_result->fetch_assoc()) {
                $prescriptions_data[] = [
                    'id' => $ehr_prescription['id'],
                    'type' => 'ehr',
                    'prescription_type' => 'E-Prescription',
                    'medication_name' => $ehr_prescription['medication'],
                    'dosage' => $ehr_prescription['dosage'],
                    'frequency' => $ehr_prescription['frequency'],
                    'duration' => $ehr_prescription['duration'],
                    'instructions' => $ehr_prescription['instructions'],
                    'doctor_name' => 'Dr. ' . $ehr_prescription['docname'],
                    'appointment_date' => $ehr_prescription['appodate'],
                    'status' => $ehr_prescription['status']
                ];
            }
        }

        // ALTERNATIVE: If no prescriptions found with appointment, try a broader search
        if (empty($prescriptions_data)) {
            // Try to find any e-prescriptions for this patient regardless of appointment status
            $alt_ehr_sql = "
                SELECT ep.*, d.docname, a.appodate, 'ehr' as prescription_type
                FROM ehr_prescriptions ep
                INNER JOIN appointment a ON ep.appoid = a.appoid
                INNER JOIN schedule s ON a.scheduleid = s.scheduleid
                INNER JOIN doctor d ON s.docid = d.docid
                WHERE a.pid = ? 
                AND ep.status = 'pending_pharmacy'
                ORDER BY ep.created_at DESC
            ";
            
            $stmt_alt_ehr = $database->prepare($alt_ehr_sql);
            $stmt_alt_ehr->bind_param("i", $patient_id);
            $stmt_alt_ehr->execute();
            $alt_ehr_result = $stmt_alt_ehr->get_result();
            
            while ($ehr_prescription = $alt_ehr_result->fetch_assoc()) {
                $prescriptions_data[] = [
                    'id' => $ehr_prescription['id'],
                    'type' => 'ehr',
                    'prescription_type' => 'E-Prescription',
                    'medication_name' => $ehr_prescription['medication'],
                    'dosage' => $ehr_prescription['dosage'],
                    'frequency' => $ehr_prescription['frequency'],
                    'duration' => $ehr_prescription['duration'],
                    'instructions' => $ehr_prescription['instructions'],
                    'doctor_name' => 'Dr. ' . $ehr_prescription['docname'],
                    'appointment_date' => $ehr_prescription['appodate'],
                    'status' => $ehr_prescription['status']
                ];
            }
        }

        // Fetch Regular prescriptions for this patient - UPDATED QUERY
        $regular_prescriptions_sql = "
            SELECT 
                p.id,
                p.medication_name,
                p.dosage,
                p.frequency,
                p.duration,
                p.instructions,
                'Regular' as prescription_type,
                p.status,
                p.doctor_id as doctor_name,
                p.created_at as prescribed_date
            FROM prescriptions p
            WHERE p.patient_id = ? 
            AND p.status = 'active' 
            AND (p.dispensed = 0 OR p.dispensed IS NULL)
            ORDER BY p.created_at DESC
        ";

        $stmt_regular = $database->prepare($regular_prescriptions_sql);
        $stmt_regular->bind_param("i", $patient_id);
        $stmt_regular->execute();
        $regular_result = $stmt_regular->get_result();

        while ($regular_prescription = $regular_result->fetch_assoc()) {
            $prescriptions_data[] = [
                'id' => $regular_prescription['id'],
                'type' => 'regular',
                'prescription_type' => 'Regular Prescription',
                'medication_name' => $regular_prescription['medication_name'],
                'dosage' => $regular_prescription['dosage'],
                'frequency' => $regular_prescription['frequency'],
                'duration' => $regular_prescription['duration'],
                'instructions' => $regular_prescription['instructions'],
                'doctor_name' => htmlspecialchars($regular_prescription['doctor_name'] ?? 'N/A'),
                'appointment_date' => $regular_prescription['prescribed_date'],
                'status' => $regular_prescription['status']
            ];
        }

        // DEBUG: If still no prescriptions, let's see what's actually in the database
        if (empty($prescriptions_data)) {
            // Debug query to see what prescriptions exist for this patient
            $debug_sql = "
                SELECT 'ehr' as type, ep.medication as name, ep.status, a.pid 
                FROM ehr_prescriptions ep 
                INNER JOIN appointment a ON ep.appoid = a.appoid 
                WHERE a.pid = ?
                UNION ALL
                SELECT 'regular' as type, p.medication_name as name, p.status, p.patient_id as pid
                FROM prescriptions p 
                WHERE p.patient_id = ?
            ";
            $stmt_debug = $database->prepare($debug_sql);
            $stmt_debug->bind_param("ii", $patient_id, $patient_id);
            $stmt_debug->execute();
            $debug_result = $stmt_debug->get_result();
            
            // Store debug info for display
            $debug_prescriptions = [];
            while ($debug_row = $debug_result->fetch_assoc()) {
                $debug_prescriptions[] = $debug_row;
            }
        }
        
        // Store in session
        $_SESSION['current_patient'] = $patient_data;
        $_SESSION['current_prescriptions'] = $prescriptions_data;
        $_SESSION['current_appoid'] = $appoid;
    }
}

// MODIFIED: Handle form submission with stock validation
if ($_POST && isset($_POST['generate_bill'])) {
    $customer_name = sanitize_input($_POST['customer_name']);
    $bill_type = sanitize_input($_POST['bill_type']);
    $patient_id_post = sanitize_input($_POST['patient_id'] ?? null);
    $appoid_post = sanitize_input($_POST['appoid'] ?? null);

    // Calculate total amount from cart
    $total_amount = 0;
    if (isset($_SESSION['pharmacy_cart']) && count($_SESSION['pharmacy_cart']) > 0) {
        foreach ($_SESSION['pharmacy_cart'] as $item) {
            $total_amount += $item['price'] * $item['quantity'];
        }
    }
    
    if ($total_amount == 0) {
        $message = "<div class='alert alert-error'>Please add products to cart before generating bill!</div>";
    } else {
        // NEW: Validate stock availability for all cart items before processing
        $stock_validation_passed = true;
        $stock_errors = [];
        
        foreach ($_SESSION['pharmacy_cart'] as $item) {
            $stock_check = check_stock_availability(
                $item['company_name'], 
                $item['product_name'], 
                $item['unit'], 
                $item['packing_size'], 
                $item['quantity'], 
                $database
            );
            
            if ($stock_check !== true) {
                $stock_validation_passed = false;
                $stock_errors[] = $stock_check;
            }
        }
        
        if (!$stock_validation_passed) {
            $message = "<div class='alert alert-error'><strong>Stock validation failed:</strong><ul style='margin: 10px 0; padding-left: 20px;'>";
            foreach ($stock_errors as $error) {
                $message .= "<li>" . htmlspecialchars($error) . "</li>";
            }
            $message .= "</ul>Please adjust quantities and try again.</div>";
        } else {
            // Stock validation passed, proceed with transaction
            try {
                // Start transaction
                $database->begin_transaction();
                
                // Insert into billing table
                $bill_no = generate_bill_no();
                $insert_billing = "INSERT INTO billing (pid, appoid, CustomerName, bill_type, date, bill_no, username, payment_status, billing_stage) 
                                  VALUES (?, ?, ?, ?, NOW(), ?, ?, 'pending', 'ready_for_payment')";
                $stmt = $database->prepare($insert_billing);
                $stmt->bind_param("iissss", $patient_id_post, $appoid_post, $customer_name, $bill_type, $bill_no, $username);
                
                if ($stmt->execute()) {
                   $billing_id = $stmt->insert_id;
                    
                    // Insert billing details and update stock
                    foreach ($_SESSION['pharmacy_cart'] as $item) {
                        // Insert billing details
                        $insert_details = "INSERT INTO billing_details 
                                          (billing_id, product_company, product_name, product_unit, packing_size, price, quantity) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $stmt_details = $database->prepare($insert_details);
                        $stmt_details->bind_param("issssdi", 
                            $billing_id, 
                            $item['company_name'], 
                            $item['product_name'], 
                            $item['unit'], 
                            $item['packing_size'], 
                            $item['price'], 
                            $item['quantity']
                        );
                        $stmt_details->execute();
                        
                        // MODIFIED: Use the dedicated deduct_stock function
                        if (!deduct_stock(
                            $item['company_name'],
                            $item['product_name'], 
                            $item['unit'], 
                            $item['packing_size'], 
                            $item['quantity'], 
                            $database
                        )) {
                            throw new Exception("Failed to update stock for {$item['product_name']}");
                        }
                    }
                    
                    // Update e-prescriptions status to dispensed
                    if ($appoid_post) {
                        $update_prescription = "UPDATE ehr_prescriptions 
                                               SET status = 'dispensed' 
                                               WHERE appoid = ?";
                        $stmt_prescription = $database->prepare($update_prescription);
                        $stmt_prescription->bind_param("i", $appoid_post);
                        $stmt_prescription->execute();
                        
                        // Update appointment workflow stage
                        $update_appointment = "UPDATE appointment 
                                              SET workflow_stage = 'payment_pending' 
                                              WHERE appoid = ?";
                        $stmt_app = $database->prepare($update_appointment);
                        $stmt_app->bind_param("i", $appoid_post);
                        $stmt_app->execute();
                    }
                    
                    // Commit transaction
                    $database->commit();
                    
                    // Clear cart
                    unset($_SESSION['pharmacy_cart']);
                    unset($_SESSION['current_patient']);
                    unset($_SESSION['current_prescriptions']);
                    unset($_SESSION['current_appoid']);
                    
                    // Redirect to pharmacy with success message
                    $_SESSION['pharmacy_message'] = "Medications dispensed successfully! Bill No: {$bill_no}. Patient should proceed to Finance for payment.";
                    header("Location: pharmacy.php");
                    exit();
                    
                } else {
                    throw new Exception("Failed to create billing record");
                }
                
            } catch (Exception $e) {
                $database->rollback();
                $message = "<div class='alert alert-error'>Error processing: " . $e->getMessage() . "</div>";
            }
        }
    }
}

// AJAX handlers
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if ($_GET['ajax'] == 'load_products') {
        $company = sanitize_input($_GET['company']);
        $products = [];
        
        $result = $database->query("SELECT DISTINCT product_name FROM stock_master 
                                   WHERE product_company = '$company' AND product_quantity > 0 
                                   ORDER BY product_name");
        while ($row = $result->fetch_assoc()) {
            $products[] = $row['product_name'];
        }
        
        echo json_encode($products);
        exit();
    }
    
    if ($_GET['ajax'] == 'load_units') {
        $company = sanitize_input($_GET['company']);
        $product = sanitize_input($_GET['product']);
        $units = [];
        
        $result = $database->query("SELECT DISTINCT product_unit FROM stock_master 
                                   WHERE product_company = '$company' AND product_name = '$product' 
                                   AND product_quantity > 0 ORDER BY product_unit");
        while ($row = $result->fetch_assoc()) {
            $units[] = $row['product_unit'];
        }
        
        echo json_encode($units);
        exit();
    }
    
    if ($_GET['ajax'] == 'load_packing_sizes') {
        $company = sanitize_input($_GET['company']);
        $product = sanitize_input($_GET['product']);
        $unit = sanitize_input($_GET['unit']);
        $sizes = [];
        
        $result = $database->query("SELECT DISTINCT packing_size FROM stock_master 
                                   WHERE product_company = '$company' AND product_name = '$product' 
                                   AND product_unit = '$unit' AND product_quantity > 0 
                                   ORDER BY packing_size");
        while ($row = $result->fetch_assoc()) {
            $sizes[] = $row['packing_size'];
        }
        
        echo json_encode($sizes);
        exit();
    }
    
    // MODIFIED: Return both price and available quantity
    if ($_GET['ajax'] == 'load_price') {
        $company = sanitize_input($_GET['company']);
        $product = sanitize_input($_GET['product']);
        $unit = sanitize_input($_GET['unit']);
        $packing_size = sanitize_input($_GET['packing_size']);
        
        $result = $database->query("SELECT product_selling_price, product_quantity FROM stock_master 
                                   WHERE product_company = '$company' AND product_name = '$product' 
                                   AND product_unit = '$unit' AND packing_size = '$packing_size' 
                                   AND product_quantity > 0");
        if ($row = $result->fetch_assoc()) {
            echo json_encode([
                'price' => $row['product_selling_price'],
                'quantity' => $row['product_quantity']
            ]);
        } else {
            echo json_encode(['price' => 0, 'quantity' => 0]);
        }
        exit();
    }
    
    // MODIFIED: Add stock check before adding to cart
    if ($_GET['ajax'] == 'add_to_cart') {
        $company_name = sanitize_input($_GET['company_name']);
        $product_name = sanitize_input($_GET['product_name']);
        $unit = sanitize_input($_GET['unit']);
        $packing_size = sanitize_input($_GET['packing_size']);
        $price = floatval($_GET['price']);
        $quantity = intval($_GET['quantity']);
        
        if ($quantity > 0 && $price > 0) {
            // Check stock availability before adding to cart
            $stock_check = check_stock_availability($company_name, $product_name, $unit, $packing_size, $quantity, $database);
            
            if ($stock_check !== true) {
                echo "error: " . $stock_check;
                exit();
            }
            
            $cart_item = [
                'company_name' => $company_name,
                'product_name' => $product_name,
                'unit' => $unit,
                'packing_size' => $packing_size,
                'price' => $price,
                'quantity' => $quantity
            ];
            
            $_SESSION['pharmacy_cart'][] = $cart_item;
            echo "success";
        } else {
            echo "error: Invalid quantity or price";
        }
        exit();
    }
}

// Handle removing item from cart
if (isset($_GET['action']) && $_GET['action'] == 'remove_from_cart') {
    $index = intval($_GET['index']);
    if (isset($_SESSION['pharmacy_cart'][$index])) {
        unset($_SESSION['pharmacy_cart'][$index]);
        $_SESSION['pharmacy_cart'] = array_values($_SESSION['pharmacy_cart']);
    }
    header("Location: pharmacy_dispense.php?patient_id=" . $patient_id);
    exit();
}

// Handle clearing cart
if (isset($_GET['action']) && $_GET['action'] == 'clear_cart') {
    unset($_SESSION['pharmacy_cart']);
    header("Location: pharmacy_dispense.php?patient_id=" . $patient_id);
    exit();
}

function generate_bill_no() {
    global $database;
    $result = $database->query("SELECT MAX(id) as max_id FROM billing");
    $row = $result->fetch_assoc();
    $id = ($row['max_id'] ?? 0) + 1;
    
    if (strlen($id) == 1) return "PHARM-000" . $id;
    if (strlen($id) == 2) return "PHARM-00" . $id;
    if (strlen($id) == 3) return "PHARM-0" . $id;
    return "PHARM-" . $id;
}

// Calculate cart total
$cart_total = 0;
if (isset($_SESSION['pharmacy_cart'])) {
    foreach ($_SESSION['pharmacy_cart'] as $item) {
        $cart_total += $item['price'] * $item['quantity'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">  
    <link rel="stylesheet" href="../css/main.css">  
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css   ">
    <title>Pharmacy Dispensing - MediCare</title>
    <style>
        :root {
            --primary: #3498db;
            --primary-dark: #2980b9;
            --secondary: #2ecc71;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #1abc9c;
            --success: #27ae60;
            --light: #f8f9fa;
            --dark: #343a40;
            --gray: #6c757d;
            --border-radius: 10px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .menu-container {
            background: white;
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            box-shadow: var(--box-shadow);
        }

        .menu-container a {
            margin: 0 10px;
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            transition: var(--transition);
        }

        .menu-container a:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin: 15px 0;
            border-left: 4px solid;
        }

        .alert-error {
            background: #fee;
            border-color: var(--danger);
            color: #c53030;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
            font-size: 16px;
            padding: 15px 30px;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .prescription-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-badge.dispensed {
            background: var(--success);
            color: white;
        }

        .status-badge.pending {
            background: var(--warning);
            color: white;
        }

        .status-badge.ehr {
            background: var(--primary);
            color: white;
        }

        .status-badge.regular {
            background: var(--info);
            color: white;
        }

        input, select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .prescription-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .prescription-item {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .prescription-item.dispensed {
            border-left: 4px solid var(--success);
            background: #f0fdf4;
        }

        .prescription-item.pending {
            border-left: 4px solid var(--warning);
            background: #fff7ed;
        }

        .medication-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .medication-name {
            font-weight: bold;
            font-size: 16px;
            color: var(--dark);
        }

        .prescription-meta {
            font-size: 12px;
            color: var(--gray);
            margin-top: 8px;
        }

        .quick-add-btn {
            background: var(--secondary);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: var(--transition);
        }

        .quick-add-btn:hover {
            background: #27ae60;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="menu-container">
            <a href="pharmacy.php"><i class="fas fa-arrow-left"></i> Back to Pharmacy</a>
        </div>

        <h1><i class="fas fa-pills"></i> Pharmacy Dispensing</h1>

        <?php if ($message): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <?php if ($from_patient && !empty($patient_data)): ?>
            <!-- Patient Summary -->
            <div class="prescription-card">
                <h3><i class="fas fa-user-injured"></i> Patient Information</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($patient_data['pname']); ?></p>
                <p><strong>Contact:</strong> <?php echo htmlspecialchars($patient_data['ptel']); ?></p>
                <!-- <p><strong>Appointment ID:</strong>  -->
                    <?php 
                    if ($appoid) {
                        echo htmlspecialchars($appoid);
                    } else {
                        // Try alternative query if first one failed
                        $alt_app_sql = "SELECT appoid FROM appointment 
                                       WHERE pid = ? 
                                       AND status = 'Complete' 
                                       AND appoid IN (SELECT DISTINCT appoid FROM ehr_prescriptions WHERE status = 'pending_pharmacy')
                                       ORDER BY appodate DESC LIMIT 1";
                        $stmt_alt = $database->prepare($alt_app_sql);
                        $stmt_alt->bind_param("i", $patient_id);
                        $stmt_alt->execute();
                        $alt_result = $stmt_alt->get_result();
                        
                        if ($alt_result->num_rows > 0) {
                            $alt_data = $alt_result->fetch_assoc();
                            $appoid = $alt_data['appoid'];
                            echo htmlspecialchars($appoid);
                        } else {
                            // echo "<span style='color: var(--warning); font-weight: bold;'>No pending appointment found</span>";
                        }
                    }
                    ?>
                </p>
            </div>

          <!-- Prescription Details Section -->
<div class="prescription-card">
    <h3><i class="fas fa-file-prescription"></i> Prescription Details</h3>
    <p style="color: var(--gray); margin-bottom: 15px;">
        Below are the medications prescribed for this patient. 
    </p>
    
    <?php if (!empty($prescriptions_data)): ?>
        <div class="prescription-details-grid">
            <?php foreach ($prescriptions_data as $prescription): ?>
                <div class="prescription-item <?php echo $prescription['status']; ?>">
                    <div class="medication-header">
                        <div class="medication-name">
                            <?php echo htmlspecialchars($prescription['medication_name']); ?>
                        </div>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <span class="status-badge <?php echo $prescription['status']; ?>">
                                <?php echo strtoupper($prescription['status']); ?>
                            </span>
                            <span class="status-badge <?php echo $prescription['type']; ?>">
                                <?php echo htmlspecialchars($prescription['prescription_type']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div style="margin: 10px 0;">
                        <p style="margin: 5px 0;">
                            <strong>Dosage:</strong> <?php echo htmlspecialchars($prescription['dosage']); ?>
                        </p>
                        <p style="margin: 5px 0;">
                            <strong>Frequency:</strong> <?php echo htmlspecialchars($prescription['frequency']); ?>
                        </p>
                        <p style="margin: 5px 0;">
                            <strong>Duration:</strong> <?php echo htmlspecialchars($prescription['duration']); ?>
                        </p>
                        <?php if (!empty($prescription['instructions'])): ?>
                            <p style="margin: 5px 0;">
                                <strong>Instructions:</strong> <?php echo htmlspecialchars($prescription['instructions']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="prescription-meta">
                        <strong>Doctor:</strong> <?php echo htmlspecialchars($prescription['doctor_name']); ?> | 
                        <strong>Date:</strong> <?php echo date('M d, Y', strtotime($prescription['appointment_date'])); ?>
                    </div>
                    
                    <?php if ($prescription['status'] == 'pending_pharmacy' || $prescription['status'] == 'active'): ?>
                        <div style="margin-top: 10px; text-align: center;">
                            <!-- <button type="button" class="quick-add-btn" 
                                    onclick="quickAddPrescription('<?php echo htmlspecialchars($prescription['medication_name']); ?>')">
                                <i class="fas fa-cart-plus"></i> Quick Add to Cart
                            </button> -->
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 30px; color: var(--gray);">
            <i class="fas fa-file-prescription" style="font-size: 48px; opacity: 0.3;"></i>
            <h4>No Active Prescriptions Found</h4>
            <p>This patient doesn't have any active prescriptions ready for dispensing.</p>
            
            <?php if (isset($debug_prescriptions) && !empty($debug_prescriptions)): ?>
                <div style="margin-top: 20px; text-align: left; background: #f8f9fa; padding: 15px; border-radius: 5px;">
                    <h5 style="color: var(--dark); margin-bottom: 10px;">Debug Information:</h5>
                    <p style="font-size: 12px; color: var(--gray);">Found these prescriptions in database but they may not be in the correct status:</p>
                    <ul style="font-size: 12px; text-align: left;">
                        <?php foreach ($debug_prescriptions as $debug): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($debug['type']); ?>:</strong> 
                                <?php echo htmlspecialchars($debug['name']); ?> 
                                (Status: <?php echo htmlspecialchars($debug['status']); ?>)
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

            <!-- Product Selection -->
            <div style="background: white; padding: 25px; border-radius: var(--border-radius); margin: 25px 0; box-shadow: var(--box-shadow);">
                <h2><i class="fas fa-search"></i> Select Medications</h2>
                <p style="color: var(--gray); margin-bottom: 15px;">
                    Search for medications in stock to add to the dispensing cart.
                </p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 20px 0;">
                    <div>
                        <label>Company</label>
                        <select id="company_name" onchange="loadProducts()" style="width: 100%; padding: 10px;">
                            <option value="">Select Company</option>
                            <?php
                            $companies = $database->query("SELECT DISTINCT product_company FROM stock_master WHERE product_quantity > 0 ORDER BY product_company");
                            while ($company = $companies->fetch_assoc()) {
                                echo "<option value='" . htmlspecialchars($company['product_company']) . "'>" . htmlspecialchars($company['product_company']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div>
                        <label>Product</label>
                        <select id="product_name" onchange="loadUnits()" style="width: 100%; padding: 10px;">
                            <option value="">Select Product</option>
                        </select>
                    </div>
                    
                    <div>
                        <label>Unit</label>
                        <select id="unit" onchange="loadPackingSizes()" style="width: 100%; padding: 10px;">
                            <option value="">Select Unit</option>
                        </select>
                    </div>
                    
                    <div>
                        <label>Packing Size</label>
                        <select id="packing_size" onchange="loadPrice()" style="width: 100%; padding: 10px;">
                            <option value="">Select Size</option>
                        </select>
                    </div>
                    
                    <div>
                        <label>Price (KES)</label>
                        <input type="text" id="price" readonly style="width: 100%; padding: 10px;">
                        <input type="hidden" id="available_quantity" value="0">
                    </div>
                    
                    <div>
                        <label>Quantity</label>
                        <input type="number" id="quantity" min="1" value="1" onchange="calculateTotal()" style="width: 100%; padding: 10px;">
                    </div>
                    
                    <div>
                        <label>Total (KES)</label>
                        <input type="text" id="total" readonly style="width: 100%; padding: 10px;">
                    </div>
                </div>
                
                <button type="button" class="btn btn-success" onclick="addToCart()">
                    <i class="fas fa-cart-plus"></i> Add to Cart
                </button>
            </div>

            <!-- Shopping Cart -->
            <div style="background: white; padding: 25px; border-radius: var(--border-radius); margin: 25px 0; box-shadow: var(--box-shadow);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2><i class="fas fa-shopping-cart"></i> Cart (<?php echo count($_SESSION['pharmacy_cart'] ?? []); ?> items)</h2>
                    <?php if (!empty($_SESSION['pharmacy_cart'])): ?>
                    <a href="?action=clear_cart&patient_id=<?php echo $patient_id; ?>" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Clear Cart
                    </a>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($_SESSION['pharmacy_cart'])): ?>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="padding: 12px; text-align: left;">Product</th>
                                <th style="padding: 12px; text-align: left;">Company</th>
                                <th style="padding: 12px; text-align: left;">Unit</th>
                                <th style="padding: 12px; text-align: right;">Price</th>
                                <th style="padding: 12px; text-align: center;">Quantity</th>
                                <th style="padding: 12px; text-align: right;">Total</th>
                                <th style="padding: 12px; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($_SESSION['pharmacy_cart'] as $index => $item): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px;"><strong><?php echo htmlspecialchars($item['product_name']); ?></strong></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($item['company_name']); ?></td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($item['unit']); ?></td>
                                <td style="padding: 12px; text-align: right;">KES <?php echo number_format($item['price'], 2); ?></td>
                                <td style="padding: 12px; text-align: center;"><?php echo $item['quantity']; ?></td>
                                <td style="padding: 12px; text-align: right; font-weight: bold;">KES <?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                <td style="padding: 12px; text-align: center;">
                                    <a href="?action=remove_from_cart&index=<?php echo $index; ?>&patient_id=<?php echo $patient_id; ?>" 
                                       style="color: var(--danger); text-decoration: none;">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f8f9fa; font-weight: bold;">
                                <td colspan="5" style="padding: 15px; text-align: right;">TOTAL AMOUNT:</td>
                                <td colspan="2" style="padding: 15px; text-align: right; font-size: 18px; color: var(--success);">
                                    KES <?php echo number_format($cart_total, 2); ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <form method="POST" style="margin-top: 30px; text-align: center;">
                        <input type="hidden" name="customer_name" value="<?php echo htmlspecialchars($patient_data['pname'] ?? ''); ?>">
                        <input type="hidden" name="bill_type" value="Cash">
                        <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                        <input type="hidden" name="appoid" value="<?php echo $appoid; ?>">
                        
                        <button type="submit" name="generate_bill" class="btn btn-success" 
                                style="padding: 20px 40px; font-size: 18px; width: 100%;">
                            <i class="fas fa-file-invoice"></i> Dispense & Generate Bill
                        </button>
                        
                        <p style="margin-top: 15px; color: var(--gray);">
                            <i class="fas fa-info-circle"></i> Patient will proceed to Finance for payment after bill generation
                        </p>
                    </form>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 20px; color: var(--gray);">
                        <i class="fas fa-shopping-cart" style="font-size: 48px; opacity: 0.3;"></i>
                        <h3>Cart is empty</h3>
                        <p>Add medications from the selection above</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-user-injured" style="font-size: 48px; color: var(--gray);"></i>
                <h3>No Patient Selected</h3>
                <p>Please select a patient from the Pharmacy dashboard to begin dispensing.</p>
                <a href="pharmacy.php" class="btn btn-primary" style="margin-top: 15px;">
                    <i class="fas fa-arrow-left"></i> Back to Pharmacy
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function loadProducts() {
            const company = document.getElementById('company_name').value;
            if (!company) return;
            
            fetch(`pharmacy_dispense.php?ajax=load_products&company=${encodeURIComponent(company)}`)
                .then(response => response.json())
                .then(products => {
                    const productSelect = document.getElementById('product_name');
                    productSelect.innerHTML = '<option value="">Select Product</option>';
                    products.forEach(product => {
                        productSelect.innerHTML += `<option value="${product}">${product}</option>`;
                    });
                    document.getElementById('unit').innerHTML = '<option value="">Select Unit</option>';
                    document.getElementById('packing_size').innerHTML = '<option value="">Select Size</option>';
                    document.getElementById('price').value = '';
                    document.getElementById('total').value = '';
                });
        }

        function loadUnits() {
            const company = document.getElementById('company_name').value;
            const product = document.getElementById('product_name').value;
            if (!company || !product) return;
            
            fetch(`pharmacy_dispense.php?ajax=load_units&company=${encodeURIComponent(company)}&product=${encodeURIComponent(product)}`)
                .then(response => response.json())
                .then(units => {
                    const unitSelect = document.getElementById('unit');
                    unitSelect.innerHTML = '<option value="">Select Unit</option>';
                    units.forEach(unit => {
                        unitSelect.innerHTML += `<option value="${unit}">${unit}</option>`;
                    });
                    document.getElementById('packing_size').innerHTML = '<option value="">Select Size</option>';
                    document.getElementById('price').value = '';
                    document.getElementById('total').value = '';
                });
        }

        function loadPackingSizes() {
            const company = document.getElementById('company_name').value;
            const product = document.getElementById('product_name').value;
            const unit = document.getElementById('unit').value;
            if (!company || !product || !unit) return;
            
            fetch(`pharmacy_dispense.php?ajax=load_packing_sizes&company=${encodeURIComponent(company)}&product=${encodeURIComponent(product)}&unit=${encodeURIComponent(unit)}`)
                .then(response => response.json())
                .then(sizes => {
                    const sizeSelect = document.getElementById('packing_size');
                    sizeSelect.innerHTML = '<option value="">Select Size</option>';
                    sizes.forEach(size => {
                        sizeSelect.innerHTML += `<option value="${size}">${size}</option>`;
                    });
                    document.getElementById('price').value = '';
                    document.getElementById('total').value = '';
                });
        }

        // MODIFIED: Handle JSON response and set max quantity
        function loadPrice() {
            const company = document.getElementById('company_name').value;
            const product = document.getElementById('product_name').value;
            const unit = document.getElementById('unit').value;
            const packingSize = document.getElementById('packing_size').value;
            if (!company || !product || !unit || !packingSize) return;
            
            fetch(`pharmacy_dispense.php?ajax=load_price&company=${encodeURIComponent(company)}&product=${encodeURIComponent(product)}&unit=${encodeURIComponent(unit)}&packing_size=${encodeURIComponent(packingSize)}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('price').value = data.price;
                    document.getElementById('available_quantity').value = data.quantity;
                    document.getElementById('quantity').max = data.quantity;
                    calculateTotal();
                });
        }

        function calculateTotal() {
            const price = parseFloat(document.getElementById('price').value) || 0;
            const quantity = parseInt(document.getElementById('quantity').value) || 0;
            document.getElementById('total').value = (price * quantity).toFixed(2);
        }

        // MODIFIED: Add client-side quantity check
        function addToCart() {
            const company = document.getElementById('company_name').value;
            const product = document.getElementById('product_name').value;
            const unit = document.getElementById('unit').value;
            const packingSize = document.getElementById('packing_size').value;
            const price = parseFloat(document.getElementById('price').value) || 0;
            const quantity = parseInt(document.getElementById('quantity').value) || 0;
            const availableQuantity = parseInt(document.getElementById('available_quantity').value) || 0;
            
            if (!company || !product || !unit || !packingSize || !price || !quantity) {
                alert('Please fill all product details correctly!');
                return;
            }
            
            // Client-side stock check
            if (quantity > availableQuantity) {
                alert(`Requested quantity (${quantity}) exceeds available stock (${availableQuantity})!`);
                return;
            }
            
            fetch(`pharmacy_dispense.php?ajax=add_to_cart&company_name=${encodeURIComponent(company)}&product_name=${encodeURIComponent(product)}&unit=${encodeURIComponent(unit)}&packing_size=${encodeURIComponent(packingSize)}&price=${price}&quantity=${quantity}`)
                .then(response => response.text())
                .then(result => {
                    if (result.startsWith('success')) {
                        alert('Product added to cart!');
                        location.reload();
                    } else if (result.startsWith('error:')) {
                        alert(result.substring(6)); // Remove "error:" prefix
                    } else {
                        alert('Error adding product to cart!');
                    }
                });
        }

        function quickAddPrescription(medicationName) {
            // Auto-fill the product search with the prescribed medication
            document.getElementById('product_name').innerHTML = '<option value="">Select Product</option>';
            
            // Search for the medication in all companies
            fetch(`pharmacy_dispense.php?ajax=load_products&company=`)
                .then(response => response.json())
                .then(products => {
                    const productSelect = document.getElementById('product_name');
                    productSelect.innerHTML = '<option value="">Select Product</option>';
                    
                    // Filter products that match the prescribed medication
                    const matchingProducts = products.filter(product => 
                        product.toLowerCase().includes(medicationName.toLowerCase())
                    );
                    
                    if (matchingProducts.length > 0) {
                        matchingProducts.forEach(product => {
                            productSelect.innerHTML += `<option value="${product}">${product}</option>`;
                        });
                        // Auto-select the first matching product
                        productSelect.value = matchingProducts[0];
                        loadUnits(); // Trigger unit loading
                        alert(`Found "${matchingProducts[0]}" in stock. Please select the appropriate unit and packing size.`);
                    } else {
                        alert(`"${medicationName}" not found in stock. Please search for alternatives.`);
                        document.getElementById('product_name').focus();
                    }
                })
                .catch(error => {
                    console.error('Error searching for medication:', error);
                    alert(`Prescribed medication "${medicationName}" not found in stock. Please search manually.`);
                });
        }
    </script>
</body>
</html>