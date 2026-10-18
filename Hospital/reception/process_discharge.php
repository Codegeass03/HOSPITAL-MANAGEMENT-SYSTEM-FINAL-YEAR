<?php
session_start();
include "includes/db_connect.php";

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: beds.php");
    exit;
}

// Get form data
$bed_id = $_POST['bed_id'] ?? null;
$discharge_date = $_POST['discharge_date'] ?? date('Y-m-d H:i:s');
$discharge_notes = $_POST['discharge_notes'] ?? '';
$final_diagnosis = $_POST['final_diagnosis'] ?? '';
$treatment_summary = $_POST['treatment_summary'] ?? '';
$follow_up_instructions = $_POST['follow_up_instructions'] ?? '';

// Billing data
$bed_charges = isset($_POST['bed_charges']) ? floatval($_POST['bed_charges']) : 0;
$additional_charges = isset($_POST['additional_charges']) ? floatval($_POST['additional_charges']) : 0;
$discount = isset($_POST['discount']) ? floatval($_POST['discount']) : 0;
$tax_amount = isset($_POST['tax_amount']) ? floatval($_POST['tax_amount']) : 0;
$total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;

$payment_method = $_POST['payment_method'] ?? 'cash';
$payment_reference = $_POST['payment_reference'] ?? '';
$paid_amount = isset($_POST['paid_amount']) ? floatval($_POST['paid_amount']) : 0;

if (!$bed_id) {
    $_SESSION['error'] = "No bed specified for discharge.";
    header("Location: beds.php");
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get bed assignment information
    $stmt = $pdo->prepare("
        SELECT ba.*, b.ward_id, w.ward_name, b.bed_number, b.bed_type, b.price_per_day
        FROM bed_assignments ba 
        JOIN beds b ON ba.bed_id = b.id 
        JOIN ward w ON b.ward_id = w.id 
        WHERE ba.bed_id = ? AND ba.status = 'active'
    ");
    $stmt->execute([$bed_id]);
    $assignment = $stmt->fetch();

    if (!$assignment) {
        throw new Exception("No active bed assignment found for this bed.");
    }

    // Update bed_assignments with discharge information
    $stmt = $pdo->prepare("
        UPDATE bed_assignments 
        SET actual_discharge_date = ?, 
            discharge_notes = ?, 
            final_diagnosis = ?, 
            treatment_summary = ?, 
            follow_up_instructions = ?, 
            status = 'discharged',
            discharged_by = ?
        WHERE bed_id = ? AND status = 'active'
    ");
    $stmt->execute([
        $discharge_date,
        $discharge_notes,
        $final_diagnosis,
        $treatment_summary,
        $follow_up_instructions,
        $_SESSION['user_id'] ?? null,
        $bed_id
    ]);
    
    // Update bed status to available
    $stmt = $pdo->prepare("UPDATE beds SET bed_status = 'available' WHERE id = ?");
    $stmt->execute([$bed_id]);
    
    // Generate bill number
    $bill_number = 'BL-' . date('Ymd-His') . '-' . rand(100, 999);
    
    // Calculate balance
    $balance_amount = $total_amount - $paid_amount;
          $bill_status = ($paid_amount >= $total_amount) ? 'paid' : 
              (($paid_amount > 0) ? 'partial' : 'pending');
    
    // Create billing record
    $stmt = $pdo->prepare("
        INSERT INTO billing (
            patient_id, bill_number, total_amount, paid_amount, balance_amount,
            status, due_date, payment_method, payment_reference, notes, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $due_date = date('Y-m-d', strtotime('+7 days'));
    
    $stmt->execute([
        $assignment['patient_id'],
        $bill_number,
        $total_amount,
        $paid_amount,
        $balance_amount,
        $bill_status,
        $due_date,
        $payment_method,
        $payment_reference,
        "Hospital stay discharge - " . $assignment['ward_name'] . " (Bed: " . $assignment['bed_number'] . ")",
        $_SESSION['user_id'] ?? null
    ]);
    
    $bill_id = $pdo->lastInsertId();
    
    // Add bed charges to bill items
    if ($bed_charges > 0) {
        // Calculate stay duration
        $admission_date = new DateTime($assignment['admission_date']);
        $discharge_date_obj = new DateTime($discharge_date);
        $days_stayed = $admission_date->diff($discharge_date_obj)->days;
        $days_stayed = max(1, $days_stayed);
        
        $stmt = $pdo->prepare("
            INSERT INTO bill_items (bill_id, item_description, quantity, unit_price, total_amount, item_type, service_date)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $bill_id,
            "Bed Charges - " . $assignment['bed_type'] . " (" . $days_stayed . " days @ Ksh " . number_format($assignment['price_per_day'], 2) . "/day)",
            $days_stayed,
            $assignment['price_per_day'],
            $bed_charges,
            'bed_charge',
            $discharge_date
        ]);
    }
    
    // Add additional charges if any
    if (isset($_POST['service_items']) && is_array($_POST['service_items'])) {
        foreach ($_POST['service_items'] as $service) {
            if (!empty($service['description']) && isset($service['amount']) && $service['amount'] > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO bill_items (bill_id, item_description, quantity, unit_price, total_amount, item_type, service_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $bill_id,
                    $service['description'],
                    $service['quantity'] ?? 1,
                    $service['amount'] / ($service['quantity'] ?? 1),
                    $service['amount'],
                    $service['type'] ?? 'other',
                    $discharge_date
                ]);
            }
        }
    }
    
    // Add discount and tax items if applicable
    if ($discount > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO bill_items (bill_id, item_description, quantity, unit_price, total_amount, item_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $bill_id,
            "Discount",
            1,
            -$discount,
            -$discount,
            'other'
        ]);
    }
    
    if ($tax_amount > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO bill_items (bill_id, item_description, quantity, unit_price, total_amount, item_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $bill_id,
            "Tax",
            1,
            $tax_amount,
            $tax_amount,
            'other'
        ]);
    }
    
    $pdo->commit();
    
    $_SESSION['success'] = "Patient discharged successfully! Bill #$bill_number generated. " . 
                          ($bill_status === 'paid' ? "Payment received in full." : 
                           "Balance due: Ksh " . number_format($balance_amount, 2));
    header("Location: beds.php?ward_id=" . $assignment['ward_id']);
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error discharging patient: " . $e->getMessage();
    header("Location: discharge.php?bed_id=" . $bed_id);
    exit;
}
?>