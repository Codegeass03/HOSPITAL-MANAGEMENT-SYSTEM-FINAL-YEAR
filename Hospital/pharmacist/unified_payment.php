<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";
include "admin/connection.php"; // MySQLi connection for pharmacy

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$patient_id = $_GET['patient_id'] ?? null;

// Get messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Fetch payment methods
try {
    $payment_methods_stmt = $pdo->query("SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY method_name");
    $payment_methods = $payment_methods_stmt->fetchAll();
} catch (PDOException $e) {
    $payment_methods = [
        ['method_code' => 'cash', 'method_name' => 'Cash', 'requires_reference' => 0],
        ['method_code' => 'mpesa', 'method_name' => 'M-Pesa', 'requires_reference' => 1],
        ['method_code' => 'card', 'method_name' => 'Card', 'requires_reference' => 1],
        ['method_code' => 'insurance', 'method_name' => 'Insurance', 'requires_reference' => 1],
        ['method_code' => 'bank_transfer', 'method_name' => 'Bank Transfer', 'requires_reference' => 1],
    ];
}

// Get patient's outstanding balances from all services
$outstanding_balances = [];
$patient_details = null;
$total_balance = 0;

if ($patient_id) {
    try {
        // Get patient details
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                   p.patient_id as patient_number
            FROM patients p 
            WHERE p.id = ?
        ");
        $stmt->execute([$patient_id]);
        $patient_details = $stmt->fetch();

        if ($patient_details) {
            // 1. Appointment Consultation Fees
            $stmt = $pdo->prepare("
                SELECT 
                    'consultation' as service_type,
                    CONCAT('Appointment #', a.appointment_number) as description,
                    a.consultation_fee as total_amount,
                    0 as paid_amount, -- Assuming no separate payment tracking for appointments
                    a.consultation_fee as balance,
                    a.id as reference_id,
                    a.appointment_date as service_date
                FROM appointments a
                WHERE a.patient_id = ? 
                AND a.status = 'completed'
                AND a.consultation_fee > 0
                AND NOT EXISTS (
                    SELECT 1 FROM bill_items bi 
                    WHERE bi.bill_id IN (SELECT id FROM billing WHERE patient_id = ?)
                    AND bi.item_type = 'consultation'
                    AND bi.service_date = a.appointment_date
                )
            ");
            $stmt->execute([$patient_id, $patient_id]);
            $consultation_balances = $stmt->fetchAll();
            $outstanding_balances = array_merge($outstanding_balances, $consultation_balances);

            // 2. Lab Orders
            $stmt = $pdo->prepare("
                SELECT 
                    'lab' as service_type,
                    CONCAT('Lab Order #', lo.order_number) as description,
                    lo.total_amount,
                    lo.paid_amount,
                    (lo.total_amount - lo.paid_amount) as balance,
                    lo.id as reference_id,
                    lo.order_date as service_date
                FROM lab_orders lo
                WHERE lo.patient_id = ? 
                AND lo.payment_status IN ('unpaid', 'partial')
                AND (lo.total_amount - lo.paid_amount) > 0
            ");
            $stmt->execute([$patient_id]);
            $lab_balances = $stmt->fetchAll();
            $outstanding_balances = array_merge($outstanding_balances, $lab_balances);

            // 3. Billing Items (General bills)
            $stmt = $pdo->prepare("
                SELECT 
                    'general' as service_type,
                    bi.item_description as description,
                    bi.total_amount,
                    0 as paid_amount, -- Assuming bill items are paid through billing table
                    bi.total_amount as balance,
                    bi.id as reference_id,
                    bi.service_date as service_date
                FROM bill_items bi
                INNER JOIN billing b ON bi.bill_id = b.id
                WHERE b.patient_id = ?
                AND b.status IN ('pending', 'partial')
                AND b.balance_amount > 0
            ");
            $stmt->execute([$patient_id]);
            $billing_balances = $stmt->fetchAll();
            $outstanding_balances = array_merge($outstanding_balances, $billing_balances);

            // 4. Bed Charges from active admissions
            $stmt = $pdo->prepare("
                SELECT 
                    'admission' as service_type,
                    CONCAT('Bed Charges - ', w.ward_name, ' (Bed: ', b.bed_number, ')') as description,
                    DATEDIFF(CURDATE(), ba.admission_date) * b.price_per_day as total_amount,
                    0 as paid_amount,
                    DATEDIFF(CURDATE(), ba.admission_date) * b.price_per_day as balance,
                    ba.id as reference_id,
                    ba.admission_date as service_date
                FROM bed_assignments ba
                INNER JOIN beds b ON ba.bed_id = b.id
                INNER JOIN ward w ON b.ward_id = w.id
                WHERE ba.patient_id = ?
                AND ba.status = 'active'
                AND DATEDIFF(CURDATE(), ba.admission_date) * b.price_per_day > 0
            ");
            $stmt->execute([$patient_id]);
            $admission_balances = $stmt->fetchAll();
            $outstanding_balances = array_merge($outstanding_balances, $admission_balances);

            // 5. Pharmacy Bills (from integrated system)
       // 5. Pharmacy Bills (from integrated system) - FIXED QUERY
mysqli_select_db($link, "hospital");

// First check if paid_amount column exists in pharmacy_billing
$check_column_query = "SHOW COLUMNS FROM pharmacy_billing LIKE 'paid_amount'";
$column_result = mysqli_query($link, $check_column_query);
$has_paid_amount = mysqli_num_rows($column_result) > 0;

if ($has_paid_amount) {
    // Use paid_amount column if it exists
    $pharmacy_balance_query = "
        SELECT 
            'pharmacy' as service_type,
            CONCAT('Pharmacy Bill #', pb.bill_no, ' - ', GROUP_CONCAT(DISTINCT pbd.product_name SEPARATOR ', ')) as description,
            SUM(pbd.total_amount) as total_amount,
            COALESCE(pb.paid_amount, 0) as paid_amount,
            (SUM(pbd.total_amount) - COALESCE(pb.paid_amount, 0)) as balance,
            pb.id as reference_id,
            pb.date as service_date
        FROM pharmacy_billing pb
        INNER JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
        WHERE pb.patient_id = '$patient_id'
        AND (SUM(pbd.total_amount) - COALESCE(pb.paid_amount, 0)) > 0
        GROUP BY pb.id, pb.paid_amount
        HAVING balance > 0
    ";
} else {
    // If no paid_amount column, assume all pharmacy bills are paid immediately
    // So we won't show any outstanding pharmacy balances
    $pharmacy_balance_query = "
        SELECT 
            'pharmacy' as service_type,
            CONCAT('Pharmacy Bill #', pb.bill_no, ' - ', GROUP_CONCAT(DISTINCT pbd.product_name SEPARATOR ', ')) as description,
            SUM(pbd.total_amount) as total_amount,
            SUM(pbd.total_amount) as paid_amount,
            0 as balance,
            pb.id as reference_id,
            pb.date as service_date
        FROM pharmacy_billing pb
        INNER JOIN pharmacy_billing_details pbd ON pb.id = pbd.billing_id
        WHERE pb.patient_id = '$patient_id'
        AND 1 = 0  -- This ensures no results since pharmacy bills are paid immediately
        GROUP BY pb.id
        HAVING balance > 0
    ";
}

$pharmacy_balance_result = mysqli_query($link, $pharmacy_balance_query);
if ($pharmacy_balance_result) {
    while ($row = mysqli_fetch_assoc($pharmacy_balance_result)) {
        // Only add if there's an actual balance
        if ($row['balance'] > 0) {
            $outstanding_balances[] = $row;
        }
    }

} else {
    // If query fails, log error but continue
    error_log("Pharmacy balance query failed: " . mysqli_error($link));
}

            // Calculate total balance
            foreach ($outstanding_balances as $balance) {
                $total_balance += $balance['balance'];
            }
        }
    } catch (PDOException $e) {
        $error_message = "Error fetching outstanding balances: " . $e->getMessage();
    }
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $payment_amount = floatval($_POST['payment_amount']);
    $payment_method = $_POST['payment_method'];
    $payment_reference = $_POST['payment_reference'] ?? '';
    $mpesa_transaction_id = $_POST['mpesa_transaction_id'] ?? '';
    $payment_notes = $_POST['payment_notes'] ?? '';
    $allocations = $_POST['allocation'] ?? [];

    try {
        $pdo->beginTransaction();

        // Generate payment number
        $payment_number = 'PAY-' . date('YmdHis') . '-' . rand(100, 999);

        // Insert main payment record
        $payment_stmt = $pdo->prepare("
            INSERT INTO payments (
                patient_id,
                payment_number,
                payment_date,
                total_amount,
                payment_method,
                payment_reference,
                mpesa_transaction_id,
                status,
                notes,
                received_by,
                created_at
            ) VALUES (?, ?, NOW(), ?, ?, ?, ?, 'completed', ?, ?, NOW())
        ");
        $payment_stmt->execute([
            $patient_id,
            $payment_number,
            $payment_amount,
            $payment_method,
            $payment_reference,
            $mpesa_transaction_id,
            $payment_notes,
            $user_id
        ]);
        $payment_id = $pdo->lastInsertId();

        // Process allocations to different services
        $remaining_amount = $payment_amount;
        
        foreach ($allocations as $service_type => $allocation_data) {
            if ($remaining_amount <= 0) break;

            foreach ($allocation_data as $reference_id => $allocated_amount) {
                if ($remaining_amount <= 0) break;

                $allocated_amount = floatval($allocated_amount);
                if ($allocated_amount <= 0) continue;

                // Determine actual amount to allocate (can't exceed remaining payment)
                $actual_allocation = min($allocated_amount, $remaining_amount);

                // Update the respective service table
                switch ($service_type) {
                    case 'consultation':
                        // For appointments, we'll create a bill item
                        $bill_stmt = $pdo->prepare("
                            INSERT INTO billing (patient_id, bill_number, bill_date, total_amount, paid_amount, balance_amount, status, payment_method, payment_reference, created_by)
                            VALUES (?, ?, NOW(), ?, ?, ?, 'paid', ?, ?, ?)
                        ");
                        $bill_number = 'BL-' . date('YmdHis') . '-' . rand(100, 999);
                        $bill_stmt->execute([
                            $patient_id,
                            $bill_number,
                            $actual_allocation,
                            $actual_allocation,
                            0,
                            $payment_method,
                            $payment_reference,
                            $user_id
                        ]);
                        $bill_id = $pdo->lastInsertId();
                        
                        // Add bill item
                        $appointment_stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
                        $appointment_stmt->execute([$reference_id]);
                        $appointment = $appointment_stmt->fetch();
                        
                        $bill_item_stmt = $pdo->prepare("
                            INSERT INTO bill_items (bill_id, item_description, quantity, unit_price, total_amount, item_type, service_date)
                            VALUES (?, ?, 1, ?, ?, 'consultation', ?)
                        ");
                        $bill_item_stmt->execute([
                            $bill_id,
                            'Consultation Fee - Appointment #' . $appointment['appointment_number'],
                            $actual_allocation,
                            $actual_allocation,
                            $appointment['appointment_date']
                        ]);
                        break;

                    case 'lab':
                        $update_stmt = $pdo->prepare("
                            UPDATE lab_orders 
                            SET paid_amount = paid_amount + ?,
                                payment_status = CASE WHEN (total_amount - (paid_amount + ?)) <= 0 THEN 'paid' ELSE 'partial' END
                            WHERE id = ?
                        ");
                        $update_stmt->execute([$actual_allocation, $actual_allocation, $reference_id]);
                        break;

                    case 'general':
                        // For general bill items, update the main billing record
                        $bill_item_stmt = $pdo->prepare("SELECT bill_id FROM bill_items WHERE id = ?");
                        $bill_item_stmt->execute([$reference_id]);
                        $bill_item = $bill_item_stmt->fetch();
                        
                        if ($bill_item) {
                            $update_stmt = $pdo->prepare("
                                UPDATE billing 
                                SET paid_amount = paid_amount + ?,
                                    balance_amount = balance_amount - ?,
                                    status = CASE WHEN (balance_amount - ?) <= 0 THEN 'paid' ELSE 'partial' END
                                WHERE id = ?
                            ");
                            $update_stmt->execute([$actual_allocation, $actual_allocation, $actual_allocation, $bill_item['bill_id']]);
                        }
                        break;

                    case 'admission':
                        // For bed charges, create a billing record
                        $bed_stmt = $pdo->prepare("
                            SELECT ba.*, b.price_per_day, w.ward_name, b.bed_number
                            FROM bed_assignments ba
                            INNER JOIN beds b ON ba.bed_id = b.id
                            INNER JOIN ward w ON b.ward_id = w.id
                            WHERE ba.id = ?
                        ");
                        $bed_stmt->execute([$reference_id]);
                        $bed_assignment = $bed_stmt->fetch();
                        
                        $days_stayed = date_diff(new DateTime($bed_assignment['admission_date']), new DateTime())->days;
                        $bed_charges = $days_stayed * $bed_assignment['price_per_day'];
                        
                        $bill_stmt = $pdo->prepare("
                            INSERT INTO billing (patient_id, bill_number, bill_date, total_amount, paid_amount, balance_amount, status, payment_method, payment_reference, created_by)
                            VALUES (?, ?, NOW(), ?, ?, ?, 'paid', ?, ?, ?)
                        ");
                        $bill_number = 'BL-' . date('YmdHis') . '-' . rand(100, 999);
                        $bill_stmt->execute([
                            $patient_id,
                            $bill_number,
                            $bed_charges,
                            $actual_allocation,
                            $bed_charges - $actual_allocation,
                            $payment_method,
                            $payment_reference,
                            $user_id
                        ]);
                        $bill_id = $pdo->lastInsertId();
                        
                        $bill_item_stmt = $pdo->prepare("
                            INSERT INTO bill_items (bill_id, item_description, quantity, unit_price, total_amount, item_type, service_date)
                            VALUES (?, ?, ?, ?, ?, 'bed_charge', ?)
                        ");
                        $bill_item_stmt->execute([
                            $bill_id,
                            'Bed Charges - ' . $bed_assignment['ward_name'] . ' (Bed: ' . $bed_assignment['bed_number'] . ')',
                            $days_stayed,
                            $bed_assignment['price_per_day'],
                            $bed_charges,
                            date('Y-m-d')
                        ]);
                        break;

                  case 'pharmacy':
    // Update pharmacy billing - handle both with and without paid_amount column
    mysqli_select_db($link, "hospital");
    
    // Check if paid_amount column exists
    $check_column_query = "SHOW COLUMNS FROM pharmacy_billing LIKE 'paid_amount'";
    $column_result = mysqli_query($link, $check_column_query);
    $has_paid_amount = mysqli_num_rows($column_result) > 0;
    
    if ($has_paid_amount) {
        // Update with paid_amount column
        $update_pharmacy_stmt = mysqli_prepare($link, "
            UPDATE pharmacy_billing 
            SET paid_amount = COALESCE(paid_amount, 0) + ?,
                payment_date = NOW(),
                payment_method = ?,
                payment_reference = ?
            WHERE id = ?
        ");
        mysqli_stmt_bind_param($update_pharmacy_stmt, "dssi", $actual_allocation, $payment_method, $payment_reference, $reference_id);
    } else {
        // Just record payment details without updating amounts
        $update_pharmacy_stmt = mysqli_prepare($link, "
            UPDATE pharmacy_billing 
            SET payment_date = NOW(),
                payment_method = ?,
                payment_reference = ?
            WHERE id = ?
        ");
        mysqli_stmt_bind_param($update_pharmacy_stmt, "ssi", $payment_method, $payment_reference, $reference_id);
    }
    
    mysqli_stmt_execute($update_pharmacy_stmt);
    break;
                }

                // Record payment allocation
                $allocation_stmt = $pdo->prepare("
                    INSERT INTO payment_allocations (
                        payment_id,
                        service_type,
                        reference_id,
                        allocated_amount,
                        created_at
                    ) VALUES (?, ?, ?, ?, NOW())
                ");
                $allocation_stmt->execute([$payment_id, $service_type, $reference_id, $actual_allocation]);

                $remaining_amount -= $actual_allocation;
            }
        }

        $pdo->commit();
        
        $_SESSION['success'] = "Payment of KSH " . number_format($payment_amount, 2) . " processed successfully!";
        // header("Location: unified_payment.php?patient_id=" . $patient_id);
        // exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Error processing payment: " . $e->getMessage();
    }
}

// Check if patient can be discharged (all bills paid)
$can_discharge = false;
if ($patient_details && $total_balance <= 0) {
    // Check if patient has active bed assignment
    $bed_stmt = $pdo->prepare("SELECT COUNT(*) as active_beds FROM bed_assignments WHERE patient_id = ? AND status = 'active'");
    $bed_stmt->execute([$patient_id]);
    $active_beds = $bed_stmt->fetch()['active_beds'];
    
    $can_discharge = ($active_beds > 0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unified Payment Processing - AuraCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .payment-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        
        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .patient-info {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .balance-summary {
            background: #fef3c7;
            color: #92400e;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .service-balances {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .service-card {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            background: white;
        }
        
        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .service-type {
            font-weight: 600;
            color: #374151;
        }
        
        .service-balance {
            font-weight: 700;
            color: #dc2626;
        }
        
        .allocation-input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            text-align: right;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .payment-method {
            position: relative;
        }
        
        .payment-method input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .payment-method label {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1.5rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        
        .payment-method input[type="radio"]:checked + label {
            border-color: #3b82f6;
            background: #eff6ff;
        }
        
        .reference-field {
            margin-bottom: 1rem;
            display: none;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-success {
            background: #059669;
            color: white;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="payment-container">
            <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h1 style="margin: 0; color: #111827; font-size: 1.875rem; font-weight: 600;">
                    <i class="fas fa-money-bill-wave"></i>
                    Unified Payment Processing
                </h1>
                <a href="billing_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            
            <?php if ($patient_details): ?>
            <!-- Patient Information -->
            <div class="patient-info">
                <h2 style="margin: 0 0 0.5rem 0; font-size: 1.5rem;">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($patient_details['patient_name']); ?>
                </h2>
                <p style="margin: 0; opacity: 0.9;">
                    Patient ID: <?php echo htmlspecialchars($patient_details['patient_number']); ?> • 
                    Phone: <?php echo htmlspecialchars($patient_details['phone']); ?>
                </p>
            </div>
            
            <!-- Balance Summary -->
            <div class="balance-summary">
                <div>
                    <h3 style="margin: 0; font-size: 1.25rem;">Total Outstanding Balance</h3>
                    <p style="margin: 0.5rem 0 0 0; font-size: 0.875rem;">
                        <?php echo count($outstanding_balances); ?> unpaid service(s)
                    </p>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 2rem; font-weight: 700;">KSH <?php echo number_format($total_balance, 2); ?></div>
                    <?php if ($can_discharge): ?>
                    <div style="color: #059669; font-weight: 600;">
                        <i class="fas fa-check-circle"></i> Ready for Discharge
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Outstanding Balances -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-file-invoice-dollar"></i>
                        Outstanding Balances
                    </h2>
                </div>
                <div class="card-body">
                    <form method="POST" id="paymentForm">
                        <input type="hidden" name="process_payment" value="1">
                        
                        <div class="service-balances">
                            <?php foreach ($outstanding_balances as $balance): ?>
                            <div class="service-card">
                                <div class="service-header">
                                    <span class="service-type">
                                        <i class="fas fa-<?php 
                                            echo match($balance['service_type']) {
                                                'consultation' => 'stethoscope',
                                                'lab' => 'flask',
                                                'general' => 'file-invoice',
                                                'admission' => 'procedures',
                                                'pharmacy' => 'pills',
                                                default => 'file'
                                            };
                                        ?>"></i>
                                        <?php echo ucfirst($balance['service_type']); ?>
                                    </span>
                                    <span class="service-balance">
                                        KSH <?php echo number_format($balance['balance'], 2); ?>
                                    </span>
                                </div>
                                <p style="margin: 0.5rem 0; font-size: 0.875rem; color: #6b7280;">
                                    <?php echo htmlspecialchars($balance['description']); ?>
                                </p>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <label style="font-size: 0.875rem;">Allocate Payment:</label>
                                    <input type="number" 
                                           name="allocation[<?php echo $balance['service_type']; ?>][<?php echo $balance['reference_id']; ?>]" 
                                           class="allocation-input" 
                                           step="0.01" 
                                           min="0" 
                                           max="<?php echo $balance['balance']; ?>"
                                           placeholder="0.00"
                                           onchange="updateTotalPayment()">
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Payment Information -->
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">
                                    <i class="fas fa-credit-card"></i>
                                    Payment Information
                                </h2>
                            </div>
                            <div class="card-body">
                                <div style="margin-bottom: 1.5rem;">
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
                                        Total Payment Amount: *
                                    </label>
                                    <input type="number" 
                                           name="payment_amount" 
                                           id="payment_amount" 
                                           class="form-control" 
                                           step="0.01" 
                                           min="0.01" 
                                           required
                                           readonly
                                           style="background: #f3f4f6; font-size: 1.25rem; font-weight: 600;">
                                </div>
                                
                                <div style="margin-bottom: 1.5rem;">
                                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500;">
                                        Payment Method: *
                                    </label>
                                    <div class="payment-methods">
                                        <?php foreach ($payment_methods as $method): ?>
                                        <div class="payment-method">
                                            <input type="radio" 
                                                   name="payment_method" 
                                                   id="method_<?php echo $method['method_code']; ?>" 
                                                   value="<?php echo $method['method_code']; ?>"
                                                   data-requires-ref="<?php echo $method['requires_reference']; ?>"
                                                   required>
                                            <label for="method_<?php echo $method['method_code']; ?>">
                                                <i class="fas fa-<?php 
                                                    echo match($method['method_code']) {
                                                        'cash' => 'money-bill-wave',
                                                        'mpesa' => 'mobile-alt',
                                                        'card' => 'credit-card',
                                                        'insurance' => 'shield-alt',
                                                        'bank_transfer' => 'university',
                                                        default => 'wallet'
                                                    };
                                                ?>"></i>
                                                <span><?php echo htmlspecialchars($method['method_name']); ?></span>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- <div class="reference-field" id="paymentReferenceField">
                                    <label for="payment_reference">Payment Reference:</label>
                                    <input type="text" 
                                           name="payment_reference" 
                                           id="payment_reference" 
                                           class="form-control" 
                                           placeholder="Enter reference number">
                                </div>
                                
                                <div class="reference-field" id="mpesaTransactionField">
                                    <label for="mpesa_transaction_id">M-Pesa Transaction ID:</label>
                                    <input type="text" 
                                           name="mpesa_transaction_id" 
                                           id="mpesa_transaction_id" 
                                           class="form-control" 
                                           placeholder="Enter M-Pesa transaction ID">
                                </div> -->
                                
                                <!-- <div style="margin-top: 1.5rem;">
                                    <label for="payment_notes">Notes (Optional):</label>
                                    <textarea name="payment_notes" 
                                              id="payment_notes" 
                                              class="form-control" 
                                              rows="3" 
                                              placeholder="Additional notes about this payment..."></textarea>
                                </div>
                                 -->
                                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                                        <i class="fas fa-check-circle"></i>
                                        Process Payment
                                    </button>
                                    
                                    <?php if ($can_discharge): ?>
                                    <a href="discharge_patient.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-success">
                                        <i class="fas fa-sign-out-alt"></i>
                                        Discharge Patient
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <!-- Patient Search -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-search"></i>
                        Find Patient for Payment
                    </h2>
                </div>
                <div class="card-body">
                    <form method="GET">
                        <div style="display: flex; gap: 1rem;">
                            <input type="text" 
                                   name="patient_id" 
                                   class="form-control" 
                                   placeholder="Enter Patient ID or Name"
                                   required>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function updateTotalPayment() {
            let total = 0;
            document.querySelectorAll('.allocation-input').forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            document.getElementById('payment_amount').value = total.toFixed(2);
        }

        // Handle payment method selection
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const requiresRef = this.dataset.requiresRef === '1';
                const isMpesa = this.value === 'mpesa';
                
                const refField = document.getElementById('paymentReferenceField');
                const mpesaField = document.getElementById('mpesaTransactionField');
                
                if (requiresRef) {
                    refField.style.display = 'block';
                    if (isMpesa) {
                        mpesaField.style.display = 'block';
                    } else {
                        mpesaField.style.display = 'none';
                    }
                } else {
                    refField.style.display = 'none';
                    mpesaField.style.display = 'none';
                }
            });
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateTotalPayment();
            document.getElementById('paymentReferenceField').style.display = 'none';
            document.getElementById('mpesaTransactionField').style.display = 'none';
        });
    </script>
</body>
</html>