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
            // DEBUG: Show patient ID being searched
            error_log("Searching for patient ID: " . $patient_id);
            
            // 1. Appointment Consultation Fees - SIMPLIFIED QUERY
            $stmt = $pdo->prepare("
                SELECT 
                    'consultation' as service_type,
                    CONCAT('Consultation - Appointment #', a.appointment_number) as description,
                    a.consultation_fee as total_amount,
                    0 as paid_amount,
                    a.consultation_fee as balance,
                    a.id as reference_id,
                    a.appointment_date as service_date
                FROM appointments a
                WHERE a.patient_id = ? 
                AND a.status = 'completed'
                AND a.consultation_fee > 0
                AND NOT EXISTS (
                    SELECT 1 FROM billing b 
                    WHERE b.patient_id = a.patient_id
                    AND b.status = 'paid'
                    AND b.total_amount = a.consultation_fee
                    AND DATE(b.bill_date) = DATE(a.appointment_date)
                )
            ");
            $stmt->execute([$patient_id]);
            $consultation_balances = $stmt->fetchAll();
            error_log("Found " . count($consultation_balances) . " consultation balances");
            $outstanding_balances = array_merge($outstanding_balances, $consultation_balances);

            // 2. Lab Orders - IMPROVED QUERY
            $stmt = $pdo->prepare("
                SELECT 
                    'lab' as service_type,
                    CONCAT('Lab Test - Order #', lo.order_number) as description,
                    lo.total_amount,
                    lo.paid_amount,
                    (lo.total_amount - lo.paid_amount) as balance,
                    lo.id as reference_id,
                    lo.order_date as service_date
                FROM lab_orders lo
                WHERE lo.patient_id = ? 
                AND lo.total_amount > 0
                AND (lo.paid_amount IS NULL OR lo.paid_amount < lo.total_amount)
                ORDER BY lo.order_date DESC
            ");
            $stmt->execute([$patient_id]);
            $lab_balances = $stmt->fetchAll();
            error_log("Found " . count($lab_balances) . " lab balances");
            
            // Filter to only include positive balances
            foreach ($lab_balances as $lab) {
                if ($lab['balance'] > 0) {
                    $outstanding_balances[] = $lab;
                }
            }

      $stmt = $pdo->prepare("
    SELECT 
        'Admission' AS service_type,
        b.bill_number AS description,
        b.total_amount,
        b.paid_amount,
        b.balance_amount AS balance,
        b.id AS reference_id,
        b.bill_date AS service_date
    FROM billing b
    WHERE b.patient_id = ?
    AND b.total_amount > 0
    AND b.id NOT IN (
        SELECT bi.bill_id
        FROM bill_items bi
        WHERE bi.item_type = 'consultation'
    )
    AND (b.balance_amount > 0 OR b.paid_amount < b.total_amount)
    ORDER BY b.bill_date DESC
");
$stmt->execute([$patient_id]);

            $billing_balances = $stmt->fetchAll();
            error_log("Found " . count($billing_balances) . " general billing balances");
            
            // Filter to only include positive balances
            foreach ($billing_balances as $bill) {
                if ($bill['balance'] > 0) {
                    $outstanding_balances[] = $bill;
                }
            }

            // 4. Bed Charges from active admissions - IMPROVED QUERY
            $stmt = $pdo->prepare("
                SELECT 
                    'admission' as service_type,
                    CONCAT('Bed Charges - ', w.ward_name, ' (Bed: ', b.bed_number, ')') as description,
                    ba.total_charges as total_amount,
                    ba.paid_amount,
                    (ba.total_charges - COALESCE(ba.paid_amount, 0)) as balance,
                    ba.id as reference_id,
                    ba.admission_date as service_date
                FROM bed_assignments ba
                INNER JOIN beds b ON ba.bed_id = b.id
                INNER JOIN ward w ON b.ward_id = w.id
                WHERE ba.patient_id = ?
                AND ba.status = 'active'
                AND ba.total_charges > 0
                AND (ba.paid_amount IS NULL OR ba.paid_amount < ba.total_charges)
            ");
            
            // If the above query fails (no total_charges column), try alternative
            try {
                $stmt->execute([$patient_id]);
                $admission_balances = $stmt->fetchAll();
            } catch (PDOException $e) {
                // Alternative query: Calculate charges dynamically
                error_log("Using alternative admission query: " . $e->getMessage());
                $stmt = $pdo->prepare("
                    SELECT 
                        'admission' as service_type,
                        CONCAT('Bed Charges - ', w.ward_name, ' (Bed: ', b.bed_number, ')') as description,
                        DATEDIFF(COALESCE(ba.actual_discharge_date, CURDATE()), ba.admission_date) * b.price_per_day as total_amount,
                        0 as paid_amount,
                        DATEDIFF(COALESCE(ba.actual_discharge_date, CURDATE()), ba.admission_date) * b.price_per_day as balance,
                        ba.id as reference_id,
                        ba.admission_date as service_date
                    FROM bed_assignments ba
                    INNER JOIN beds b ON ba.bed_id = b.id
                    INNER JOIN ward w ON b.ward_id = w.id
                    WHERE ba.patient_id = ?
                    AND ba.status = 'active'
                    AND DATEDIFF(COALESCE(ba.actual_discharge_date, CURDATE()), ba.admission_date) * b.price_per_day > 0
                ");
                $stmt->execute([$patient_id]);
                $admission_balances = $stmt->fetchAll();
            }
            
            error_log("Found " . count($admission_balances) . " admission balances");
            
            // Filter to only include positive balances
            foreach ($admission_balances as $admission) {
                if ($admission['balance'] > 0) {
                    $outstanding_balances[] = $admission;
                }
            }

            // 5. Pharmacy Bills
            mysqli_select_db($link, "hospital");
            
            // First check if the patient_id column exists in pharmacy_billing
            $check_column_query = "SHOW COLUMNS FROM pharmacy_billing LIKE 'patient_id'";
            $column_result = mysqli_query($link, $check_column_query);
            
            if (mysqli_num_rows($column_result) > 0) {
                // Column exists, use it
                $pharmacy_balance_query = "
                    SELECT 
                        'pharmacy' as service_type,
                        CONCAT('Pharmacy - Bill #', pb.bill_no, ' (', pb.CustomerName, ')') as description,
                        pb.total_amount,
                        pb.paid_amount,
                        pb.balance_amount as balance,
                        pb.id as reference_id,
                        pb.date as service_date
                    FROM pharmacy_billing pb
                    WHERE pb.patient_id = '$patient_id'
                    AND pb.total_amount > 0
                    AND (pb.paid_amount IS NULL OR pb.paid_amount < pb.total_amount OR pb.balance_amount > 0)
                ";
            } else {
                // Column doesn't exist, use alternative approach
                // Get patient name first
                $patient_stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as patient_name FROM patients WHERE id = ?");
                $patient_stmt->execute([$patient_id]);
                $patient_name = $patient_stmt->fetchColumn();
                
                if ($patient_name) {
                    $pharmacy_balance_query = "
                        SELECT 
                            'pharmacy' as service_type,
                            CONCAT('Pharmacy - Bill #', pb.bill_no, ' (', pb.CustomerName, ')') as description,
                            pb.total_amount,
                            pb.paid_amount,
                            pb.balance_amount as balance,
                            pb.id as reference_id,
                            pb.date as service_date
                        FROM pharmacy_billing pb
                        WHERE pb.CustomerName LIKE '%" . mysqli_real_escape_string($link, $patient_name) . "%'
                        AND pb.total_amount > 0
                        AND (pb.paid_amount IS NULL OR pb.paid_amount < pb.total_amount OR pb.balance_amount > 0)
                    ";
                } else {
                    $pharmacy_balance_query = "SELECT 1 WHERE 1 = 0"; // Empty query
                }
            }
            
            error_log("Pharmacy query: " . $pharmacy_balance_query);
            
            $pharmacy_balance_result = mysqli_query($link, $pharmacy_balance_query);
            if ($pharmacy_balance_result) {
                $pharmacy_count = mysqli_num_rows($pharmacy_balance_result);
                error_log("Found " . $pharmacy_count . " pharmacy balances");
                while ($row = mysqli_fetch_assoc($pharmacy_balance_result)) {
                    // Only add if there's an actual balance
                    if (($row['balance'] ?? ($row['total_amount'] - $row['paid_amount'])) > 0) {
                        $outstanding_balances[] = $row;
                    }
                }
            } else {
                error_log("Pharmacy query error: " . mysqli_error($link));
            }

            // DEBUG: Show all balances found
            error_log("Total outstanding balances found: " . count($outstanding_balances));
            foreach ($outstanding_balances as $index => $balance) {
                error_log("Balance $index: " . $balance['service_type'] . " - " . $balance['description'] . " - KSH " . ($balance['balance'] ?? 'N/A'));
            }

            // Calculate total balance
            foreach ($outstanding_balances as $balance) {
                $total_balance += ($balance['balance'] ?? 0);
            }
            error_log("Total balance calculated: KSH " . $total_balance);
        }
    } catch (PDOException $e) {
        $error_message = "Error fetching outstanding balances: " . $e->getMessage();
        error_log($error_message);
    }
}

// Variables for receipt data
$payment_receipt_data = null;

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['process_payment'])) {
        $payment_amount = floatval($_POST['payment_amount']);
        $payment_method = $_POST['payment_method'];
        $payment_reference = $_POST['payment_reference'] ?? '';
        $payment_notes = $_POST['payment_notes'] ?? '';
        $allocations = $_POST['allocation'] ?? [];

        error_log("Payment processing started for patient $patient_id");
        error_log("Payment amount: $payment_amount");
        error_log("Payment method: $payment_method");
        error_log("Allocations: " . print_r($allocations, true));

        try {
            $pdo->beginTransaction();

            // Generate payment number
            $payment_number = 'PAY-' . date('YmdHis') . '-' . rand(100, 999);

            // Insert main payment record
            $payment_stmt = $pdo->prepare("
                INSERT INTO payments (
                    patient_id,
                    payment_number,
                    total_amount,
                    payment_method,
                    payment_reference,
                    status,
                    notes,
                    received_by,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, 'completed', ?, ?, NOW())
            ");
            $payment_stmt->execute([
                $patient_id,
                $payment_number,
                $payment_amount,
                $payment_method,
                $payment_reference,
                $payment_notes,
                $user_id
            ]);
            $payment_id = $pdo->lastInsertId();
            error_log("Payment record created with ID: $payment_id, Number: $payment_number");

            // Store allocation details for receipt
            $allocation_details = [];

            // Process allocations to different services
            $remaining_amount = $payment_amount;
            $total_allocated = 0;
            
            foreach ($allocations as $service_type => $allocation_data) {
                if ($remaining_amount <= 0) break;

                foreach ($allocation_data as $reference_id => $allocated_amount) {
                    if ($remaining_amount <= 0) break;

                    $allocated_amount = floatval($allocated_amount);
                    if ($allocated_amount <= 0) continue;

                    // Determine actual amount to allocate
                    $actual_allocation = min($allocated_amount, $remaining_amount);
                    error_log("Allocating $actual_allocation to $service_type (ID: $reference_id)");

                    // Update the respective service table
                    switch ($service_type) {
                        case 'consultation':
    // For appointments, create a bill item
    $appointment_stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
    $appointment_stmt->execute([$reference_id]);
    $appointment = $appointment_stmt->fetch();
    
    if ($appointment) {
        // Check if a bill already exists for this consultation
        $check_bill_stmt = $pdo->prepare("
            SELECT b.id 
            FROM billing b
            INNER JOIN bill_items bi ON b.id = bi.bill_id
            WHERE b.patient_id = ?
            AND bi.item_type = 'consultation'
            AND bi.item_description LIKE CONCAT('%Appointment #', ?, '%')
            AND b.status = 'paid'
        ");
        $check_bill_stmt->execute([$patient_id, $appointment['appointment_number']]);
        $existing_bill = $check_bill_stmt->fetch();
        
        if (!$existing_bill) {
            // Create new bill only if one doesn't exist
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
        }
        
        $allocation_details[] = [
            'service_type' => 'Consultation',
            'description' => 'Appointment #' . $appointment['appointment_number'],
            'amount' => $actual_allocation
        ];
        error_log("Consultation payment processed for appointment #" . $appointment['appointment_number']);
    }
    break;
                        case 'lab':
                            $lab_stmt = $pdo->prepare("SELECT * FROM lab_orders WHERE id = ?");
                            $lab_stmt->execute([$reference_id]);
                            $lab_order = $lab_stmt->fetch();
                            
                            if ($lab_order) {
                                $update_stmt = $pdo->prepare("
                                    UPDATE lab_orders 
                                    SET paid_amount = COALESCE(paid_amount, 0) + ?,
                                        payment_status = CASE 
                                            WHEN (total_amount - (COALESCE(paid_amount, 0) + ?)) <= 0 THEN 'paid' 
                                            ELSE 'partial' 
                                        END,
                                        completed_at = NOW()
                                    WHERE id = ?
                                ");
                                $update_stmt->execute([$actual_allocation, $actual_allocation, $reference_id]);
                                
                                $allocation_details[] = [
                                    'service_type' => 'Laboratory',
                                    'description' => 'Lab Order #' . $lab_order['order_number'],
                                    'amount' => $actual_allocation
                                ];
                                error_log("Lab payment processed for order #" . $lab_order['order_number']);
                            }
                            break;

                        case 'general':
                            $billing_stmt = $pdo->prepare("SELECT * FROM billing WHERE id = ?");
                            $billing_stmt->execute([$reference_id]);
                            $billing = $billing_stmt->fetch();
                            
                            if ($billing) {
                                $update_stmt = $pdo->prepare("
                                    UPDATE billing 
                                    SET paid_amount = COALESCE(paid_amount, 0) + ?,
                                        balance_amount = balance_amount - ?,
                                        status = CASE 
                                            WHEN (balance_amount - ?) <= 0 THEN 'paid' 
                                            ELSE 'partial' 
                                        END,
                                        payment_method = ?,
                                        payment_reference = ?,
                                        updated_at = NOW()
                                    WHERE id = ?
                                ");
                                $update_stmt->execute([
                                    $actual_allocation, 
                                    $actual_allocation, 
                                    $actual_allocation,
                                    $payment_method,
                                    $payment_reference,
                                    $reference_id
                                ]);
                                
                                $allocation_details[] = [
                                    'service_type' => 'General',
                                    'description' => $billing['description'] ?? 'General Bill',
                                    'amount' => $actual_allocation
                                ];
                                error_log("General bill payment processed");
                            }
                            break;

                        case 'admission':
    $bed_stmt = $pdo->prepare("
        SELECT ba.*, b.price_per_day, w.ward_name, b.bed_number
        FROM bed_assignments ba
        INNER JOIN beds b ON ba.bed_id = b.id
        INNER JOIN ward w ON b.ward_id = w.id
        WHERE ba.id = ?
    ");
    $bed_stmt->execute([$reference_id]);
    $bed_assignment = $bed_stmt->fetch();
    
    if ($bed_assignment) {
        // Calculate total charges if not already in database
        if (!empty($bed_assignment['total_charges'])) {
            $total_charges = $bed_assignment['total_charges'];
        } else {
            $days_stayed = date_diff(
                new DateTime($bed_assignment['admission_date']), 
                new DateTime($bed_assignment['discharge_date'] ?? 'now')
            )->days;
            $total_charges = $days_stayed * $bed_assignment['price_per_day'];
        }
        
        // Update bed assignment with payment
        $update_bed_stmt = $pdo->prepare("
            UPDATE bed_assignments 
            SET paid_amount = COALESCE(paid_amount, 0) + ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $update_bed_stmt->execute([$actual_allocation, $reference_id]);
        
        // Also create a billing record for tracking
        $bill_stmt = $pdo->prepare("
            INSERT INTO billing (patient_id, bill_number, bill_date, total_amount, paid_amount, balance_amount, status, payment_method, payment_reference, created_by)
            VALUES (?, ?, NOW(), ?, ?, ?, 'partial', ?, ?, ?)
        ");
        $bill_number = 'BL-' . date('YmdHis') . '-' . rand(100, 999);
        $bill_stmt->execute([
            $patient_id,
            $bill_number,
            $total_charges,
            $actual_allocation,
            $total_charges - $actual_allocation,
            $payment_method,
            $payment_reference,
            $user_id
        ]);
        
        $allocation_details[] = [
            'service_type' => 'Admission',
            'description' => 'Bed Charges - ' . $bed_assignment['ward_name'],
            'amount' => $actual_allocation
        ];
        error_log("Admission payment processed for ward: " . $bed_assignment['ward_name']);
    }
    break;

// REPLACE WITH THIS UPDATED CODE:
case 'admission':
    $bed_stmt = $pdo->prepare("
        SELECT ba.*, b.price_per_day, w.ward_name, b.bed_number
        FROM bed_assignments ba
        INNER JOIN beds b ON ba.bed_id = b.id
        INNER JOIN ward w ON b.ward_id = w.id
        WHERE ba.id = ?
    ");
    $bed_stmt->execute([$reference_id]);
    $bed_assignment = $bed_stmt->fetch();
    
    if ($bed_assignment) {
        // Calculate total charges if not already in database
        if (!empty($bed_assignment['total_charges'])) {
            $total_charges = $bed_assignment['total_charges'];
        } else {
            $days_stayed = date_diff(
                new DateTime($bed_assignment['admission_date']), 
                new DateTime($bed_assignment['discharge_date'] ?? 'now')
            )->days;
            $total_charges = $days_stayed * $bed_assignment['price_per_day'];
        }
        
        // Update bed assignment with payment
        $update_bed_stmt = $pdo->prepare("
            UPDATE bed_assignments 
            SET paid_amount = COALESCE(paid_amount, 0) + ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $update_bed_stmt->execute([$actual_allocation, $reference_id]);
        
        // Also create a billing record for tracking
        $bill_stmt = $pdo->prepare("
            INSERT INTO billing (patient_id, bill_number, bill_date, total_amount, paid_amount, balance_amount, status, payment_method, payment_reference, created_by)
            VALUES (?, ?, NOW(), ?, ?, ?, 'partial', ?, ?, ?)
        ");
        $bill_number = 'BL-' . date('YmdHis') . '-' . rand(100, 999);
        $bill_stmt->execute([
            $patient_id,
            $bill_number,
            $total_charges,
            $actual_allocation,
            $total_charges - $actual_allocation,
            $payment_method,
            $payment_reference,
            $user_id
        ]);
        
        // UPDATED: Changed from 'Admission' to 'Admission Charges'
        $allocation_details[] = [
            'service_type' => 'Admission Charges',
            'description' => $bed_assignment['ward_name'] . ' - Bed ' . $bed_assignment['bed_number'],
            'amount' => $actual_allocation
        ];
        error_log("Admission payment processed for ward: " . $bed_assignment['ward_name']);
    }
    break;

                        case 'pharmacy':
                            // Update pharmacy billing
                            mysqli_select_db($link, "hospital");
                            
                            // Get current bill details
                            $bill_query = "SELECT bill_no, CustomerName, total_amount, paid_amount, balance_amount FROM pharmacy_billing WHERE id = ?";
                            $bill_stmt = mysqli_prepare($link, $bill_query);
                            mysqli_stmt_bind_param($bill_stmt, "i", $reference_id);
                            mysqli_stmt_execute($bill_stmt);
                            $bill_result = mysqli_stmt_get_result($bill_stmt);
                            $current_bill = mysqli_fetch_assoc($bill_result);
                            
                            if ($current_bill) {
                                $new_paid_amount = ($current_bill['paid_amount'] ?? 0) + $actual_allocation;
                                $new_balance = ($current_bill['balance_amount'] ?? $current_bill['total_amount'] ?? 0) - $actual_allocation;
                                
                                // Determine new status
                                if ($new_balance <= 0) {
                                    $new_status = 'paid';
                                } elseif ($new_paid_amount > 0) {
                                    $new_status = 'partial';
                                } else {
                                    $new_status = 'pending';
                                }
                                
                                // Update pharmacy_billing
                                $update_pharmacy_stmt = mysqli_prepare($link, "
                                    UPDATE pharmacy_billing 
                                    SET paid_amount = ?,
                                        balance_amount = ?,
                                        payment_status = ?,
                                        updated_at = NOW()
                                    WHERE id = ?
                                ");
                                mysqli_stmt_bind_param($update_pharmacy_stmt, "ddsi", 
                                    $new_paid_amount, 
                                    $new_balance, 
                                    $new_status,
                                    $reference_id
                                );
                                mysqli_stmt_execute($update_pharmacy_stmt);
                                
                                $allocation_details[] = [
                                    'service_type' => 'Pharmacy',
                                    'description' => 'Pharmacy Bill #' . ($current_bill['bill_no'] ?? ''),
                                    'amount' => $actual_allocation
                                ];
                                error_log("Pharmacy payment processed for bill #" . ($current_bill['bill_no'] ?? ''));
                            }
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
                    $total_allocated += $actual_allocation;
                }
            }

            error_log("Total allocated: $total_allocated, Remaining: $remaining_amount");

            $pdo->commit();
            
            // Prepare receipt data
            $payment_receipt_data = [
                'payment_id' => $payment_id,
                'payment_number' => $payment_number,
                'patient_name' => $patient_details['patient_name'],
                'patient_id' => $patient_details['patient_number'],
                'total_amount' => $payment_amount,
                'payment_method' => $payment_method,
                'payment_reference' => $payment_reference,
                'allocations' => $allocation_details,
                'received_by' => $user_id
            ];
            
            error_log("Receipt data prepared with " . count($allocation_details) . " allocations");
            
            $_SESSION['success'] = "Payment of KSH " . number_format($payment_amount, 2) . " processed successfully!";
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Error processing payment: " . $e->getMessage();
            error_log($error_message);
        }
    }
}

// Check if patient can be discharged
$can_discharge = false;
if ($patient_details && $total_balance <= 0) {
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

        .main-content {
            flex: 1;
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

        .section-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--primarycolor);
            margin: 30px 0 20px 0;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--accentcolor);
        }

        .info-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }

        .info-label {
            font-weight: 600;
            color: var(--primarydark);
        }

        .bill-summary {
            background: #f0fdfa;
            border: 2px solid var(--accentcolor);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .bill-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .bill-row:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 18px;
            color: var(--primarydark);
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px solid var(--primarycolor);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primarycolor);
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--primarycolor);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primarydark);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .service-item {
            background: #f9fafb;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

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

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-paid {
            background: #d1fae5;
            color: #065f46;
        }

        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            max-width: 400px;
            padding: 10px 15px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        /* Service type badges with distinct colors */
        .service-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-left: 5px;
        }

        .badge-consultation { background: #dbeafe; color: #1e40af; }
        .badge-pharmacy { background: #dcfce7; color: #166534; }
        .badge-lab { background: #fef3c7; color: #92400e; }
        .badge-admission { background: #f3e8ff; color: #7c3aed; }
        .badge-general { background: #e0f2fe; color: #0c4a6e; }

        /* Highlight different service types */
        .service-consultation { border-left: 3px solid #1e40af; }
        .service-pharmacy { border-left: 3px solid #166534; }
        .service-lab { border-left: 3px solid #92400e; }
        .service-admission { border-left: 3px solid #7c3aed; }
        .service-general { border-left: 3px solid #0c4a6e; }

        /* Receipt Styles */
        .receipt-container {
            max-width: 500px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            font-family: 'Courier New', monospace;
            display: none;
        }

        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #e5e7eb;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }

        .receipt-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--primarycolor);
        }

        .receipt-subtitle {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .receipt-details {
            margin-bottom: 1.5rem;
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .receipt-divider {
            border-top: 1px dashed #e5e7eb;
            margin: 1rem 0;
        }

        .receipt-total {
            font-weight: bold;
            font-size: 1.125rem;
            color: var(--primarydark);
        }

        .receipt-footer {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.75rem;
            color: #6b7280;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .receipt-container, .receipt-container * {
                visibility: visible;
            }
            .receipt-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-shadow: none;
                margin: 0;
                padding: 1rem;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Main Content -->
        <div class="main-content">
            <table border="0" width="100%" style="border-spacing: 0;margin:0;padding:0;">
                <tr>
                    <td colspan="2" class="nav-bar">
                        <div>
                            <p style="font-size: 28px;font-weight: 700;color: var(--primarycolor);margin:0;">Unified Payment Processing</p>
                            <p style="font-size: 14px;color: #64748b;margin-top:5px;">Process payments for all services</p>
                        </div>
                        <div style="text-align: right;">
                            <p style="font-size: 14px;color: #64748b;margin:0;">Today's Date</p>
                            <p style="font-size: 16px;font-weight: 600;color: var(--primarydark);margin:5px 0 0 0;"><?php echo date('F j, Y'); ?></p>
                        </div>
                    </td>
                </tr>

                <!-- Success/Error Alerts -->
                <?php if ($success_message): ?>
                <tr>
                    <td colspan="2">
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                            <?php if ($payment_receipt_data): ?>
                            <button onclick="printReceipt()" class="btn btn-primary" style="margin-left: 10px; padding: 5px 10px;">
                                <i class="fas fa-print"></i> Print Receipt
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ($error_message): ?>
                <tr>
                    <td colspan="2">
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>

                <!-- Statistics -->
                <?php if ($patient_details && !$payment_receipt_data): ?>
                <tr>
                    <td colspan="2">
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value">KSh <?php echo number_format($total_balance, 2); ?></div>
                                <div class="stat-label">Total Balance</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo count($outstanding_balances); ?></div>
                                <div class="stat-label">Pending Services</div>
                            </div>
                            <!-- <div class="stat-card">
                                <div class="stat-value">
                                    <?php if ($can_discharge): ?>
                                        <i class="fas fa-check-circle" style="color: #10b981;"></i> Yes
                                    <?php else: ?>
                                        <i class="fas fa-times-circle" style="color: #ef4444;"></i> No
                                    <?php endif; ?>
                                </div>
                                <div class="stat-label">Ready for Discharge</div>
                            </div> -->
                        </div>
                    </td>
                </tr>
                <?php endif; ?>

                <?php if ($patient_details && !$payment_receipt_data): ?>
                <!-- Patient Information -->
                <tr>
                    <td colspan="2">
                        <div class="info-card">
                            <h3 style="color: var(--primarycolor); margin-bottom: 15px;">Patient Information</h3>
                            <div class="info-row">
                                <span class="info-label">Name:</span>
                                <span><?php echo htmlspecialchars($patient_details['patient_name']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Patient ID:</span>
                                <span><?php echo htmlspecialchars($patient_details['patient_number']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Contact:</span>
                                <span><?php echo htmlspecialchars($patient_details['phone']); ?></span>
                            </div>
                            <?php if (count($outstanding_balances) > 0): ?>
                            <div class="info-row">
                                <span class="info-label">Services Due:</span>
                                <span>
                                    <?php
                                    $service_counts = [];
                                    foreach ($outstanding_balances as $balance) {
                                        $type = $balance['service_type'];
                                        $service_counts[$type] = ($service_counts[$type] ?? 0) + 1;
                                    }
                                    echo implode(', ', array_map(function($type, $count) {
                                        return ucfirst($type) . " ($count)";
                                    }, array_keys($service_counts), $service_counts));
                                    ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

                <!-- Payment Form -->
                <?php if (count($outstanding_balances) > 0): ?>
                <tr>
                    <td colspan="2">
                        <div style="background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                            <h3 style="color: var(--primarycolor); margin-bottom: 20px; font-size: 22px;">Process Payment</h3>
                            
                            <!-- Bill Summary -->
                            <div class="bill-summary">
                                <h3 style="color: var(--primarydark); margin-bottom: 15px;">Outstanding Services</h3>
                                <?php foreach ($outstanding_balances as $balance): 
                                    $badge_class = 'badge-' . $balance['service_type'];
                                    $service_class = 'service-' . $balance['service_type'];
                                ?>
                                <div class="service-item <?php echo $service_class; ?>">
                                    <div>
                                        <strong><?php echo htmlspecialchars($balance['description']); ?></strong>
                                        <br>
                                        <small style="color:#64748b;">
                                            <?php echo date('M d, Y', strtotime($balance['service_date'])); ?> • 
                                            <span class="service-badge <?php echo $badge_class; ?>">
                                                <?php echo ucfirst($balance['service_type']); ?>
                                            </span>
                                        </small>
                                    </div>
                                    <span style="font-weight: 600;">KSh <?php echo number_format($balance['balance'], 2); ?></span>
                                </div>
                                <?php endforeach; ?>
                                <div class="bill-row">
                                    <span>Total Amount Due:</span>
                                    <span style="font-weight: 700; color: var(--primarydark); font-size: 20px;">
                                        KSh <?php echo number_format($total_balance, 2); ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Payment Form -->
                            <form method="POST" action="">
                                <input type="hidden" name="payment_amount" id="payment_amount" value="<?php echo $total_balance; ?>">
                                
                                <!-- Hidden allocation fields -->
                                <?php foreach ($outstanding_balances as $balance): ?>
                                <input type="hidden" 
                                       name="allocation[<?php echo $balance['service_type']; ?>][<?php echo $balance['reference_id']; ?>]" 
                                       value="<?php echo $balance['balance']; ?>">
                                <?php endforeach; ?>

                                <div class="form-group">
                                    <label class="form-label">Payment Method *</label>
                                    <select class="form-control" name="payment_method" id="payment_method" required onchange="toggleTransactionField()">
                                        <option value="">-- Select Payment Method --</option>
                                        <?php foreach ($payment_methods as $method): ?>
                                        <option value="<?php echo $method['method_code']; ?>" data-requires-ref="<?php echo $method['requires_reference']; ?>">
                                            <?php echo $method['method_name']; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                              

                               

                                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                                    <a href="billing_dashboard.php" class="btn" style="background: #e5e7eb; color: #374151;">Cancel</a>
                                    <button type="submit" name="process_payment" class="btn btn-success" id="processPaymentBtn">
                                        <i class="fas fa-money-bill-wave"></i> Process Full Payment
                                    </button>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <tr>
                    <td colspan="2">
                        <div style="background: white; border-radius: 12px; padding: 30px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                            <div style="font-size: 48px; color: #10b981; margin-bottom: 20px;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 style="color: var(--primarycolor); margin-bottom: 10px;">No Outstanding Balances</h3>
                            <p style="color: #64748b; margin-bottom: 20px;">
                                <?php echo htmlspecialchars($patient_details['patient_name']); ?> has no pending payments.
                            </p>
                            <a href="billing_dashboard.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>

                <?php elseif (!$patient_details && !$payment_receipt_data): ?>
                <!-- Patient Search -->
                <tr>
                    <td colspan="2">
                        <div style="background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: center;">
                            <h3 style="color: var(--primarycolor); margin-bottom: 20px; font-size: 22px;">Find Patient for Payment</h3>
                            <form method="GET" style="max-width: 500px; margin: 0 auto;">
                                <div style="display: flex; gap: 10px;">
                                    <input type="text" 
                                           name="patient_id" 
                                           class="form-control" 
                                           placeholder="Enter Patient ID"
                                           required
                                           style="flex: 1;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                </div>
                                <p style="margin-top: 15px; color: #64748b; font-size: 14px;">
                                    <i class="fas fa-info-circle"></i> Enter the patient ID to view and process their outstanding payments
                                </p>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- Receipt Print Section -->
    <?php if ($payment_receipt_data): ?>
    <div class="receipt-container" id="receipt">
        <div class="receipt-header">
            <div class="receipt-title">AURACARE HMS</div>
            <div class="receipt-subtitle">Medical Payment Receipt</div>
        </div>
        
        <div class="receipt-details">
            <div class="receipt-row">
                <span>Receipt No:</span>
                <span><?php echo $payment_receipt_data['payment_number']; ?></span>
            </div>
            
            <div class="receipt-row">
                <span>Patient:</span>
                <span><?php echo $payment_receipt_data['patient_name']; ?></span>
            </div>
            <div class="receipt-row">
                <span>Patient ID:</span>
                <span><?php echo $payment_receipt_data['patient_id']; ?></span>
            </div>
            <div class="receipt-row">
                <span>Payment Method:</span>
                <span><?php echo strtoupper($payment_receipt_data['payment_method']); ?></span>
            </div>
            <?php if ($payment_receipt_data['payment_reference']): ?>
            <div class="receipt-row">
                <span>Reference:</span>
                <span><?php echo $payment_receipt_data['payment_reference']; ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="receipt-divider"></div>
        
        <div class="receipt-items">
            <?php foreach ($payment_receipt_data['allocations'] as $allocation): 
                $service_class = strtolower(str_replace(' ', '-', $allocation['service_type']));
            ?>
            <div class="receipt-row">
                <span>
                    <?php echo $allocation['service_type']; ?>
                    <span class="service-badge badge-<?php echo $service_class; ?>">
                        <?php echo $allocation['service_type']; ?>
                    </span>
                </span>
                <span>KSH <?php echo number_format($allocation['amount'], 2); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="receipt-divider"></div>
        
        <div class="receipt-row receipt-total">
            <span>TOTAL PAID:</span>
            <span>KSH <?php echo number_format($payment_receipt_data['total_amount'], 2); ?></span>
        </div>
        
        <div class="receipt-footer">
            <p>Thank you for your payment!</p>
            <p>For inquiries, contact: +254 700 000 000</p>
            <p>Generated: <?php echo date('Y-m-d H:i:s'); ?></p>
            <p>This is a computer-generated receipt</p>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function toggleTransactionField() {
            const methodSelect = document.getElementById('payment_method');
            const selectedOption = methodSelect.options[methodSelect.selectedIndex];
            const requiresRef = selectedOption.getAttribute('data-requires-ref');
            const transactionField = document.getElementById('transaction_field');
            
            if (requiresRef === '1') {
                transactionField.style.display = 'block';
            } else {
                transactionField.style.display = 'none';
            }
        }

        function printReceipt() {
            // Show receipt container
            const receipt = document.getElementById('receipt');
            if (receipt) {
                receipt.style.display = 'block';
                
                // Print receipt
                window.print();
                
                // Hide receipt container after printing
                setTimeout(() => {
                    receipt.style.display = 'none';
                }, 500);
            }
        }

        // After successful payment, redirect to dashboard
        <?php if ($payment_receipt_data): ?>
        setTimeout(function() {
            window.location.href = 'billing_dashboard.php?success=Payment+processed+successfully';
        }, 3000);
        <?php endif; ?>

        // Auto-print receipt if payment was just processed
        <?php if ($payment_receipt_data): ?>
        setTimeout(() => {
            printReceipt();
        }, 1000);
        <?php endif; ?>

        // Function to update bill status after payment
function updateBillStatusAfterPayment($pdo, $bill_id, $payment_amount) {
    try {
        $stmt = $pdo->prepare("SELECT paid_amount, balance_amount FROM billing WHERE id = ?");
        $stmt->execute([$bill_id]);
        $bill = $stmt->fetch();
        
        if ($bill) {
            $new_paid = $bill['paid_amount'] + $payment_amount;
            $new_balance = $bill['balance_amount'] - $payment_amount;
            $status = $new_balance > 0 ? 'pending' : 'paid';
            
            $stmt = $pdo->prepare("
                UPDATE billing 
                SET paid_amount = ?, balance_amount = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([$new_paid, $new_balance, $status, $bill_id]);
            
            return true;
        }
    } catch (Exception $e) {
        error_log("Error updating bill status: " . $e->getMessage());
    }
    return false;
}

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Set default payment method to cash
            document.getElementById('payment_method').value = 'cash';
            
            // Focus on patient search if no patient selected
            <?php if (!$patient_details && !$payment_receipt_data): ?>
            document.querySelector('input[name="patient_id"]').focus();
            <?php endif; ?>
        });
    </script>
</body>
</html>