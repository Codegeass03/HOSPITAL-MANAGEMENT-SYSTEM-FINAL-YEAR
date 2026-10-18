<?php
session_start();
include "includes/db_connect.php";

// Check if bed_id is provided
$bed_id = $_GET['bed_id'] ?? null;

if (!$bed_id) {
    $_SESSION['error'] = "No bed specified for discharge.";
    header("Location: beds.php");
    exit;
}

// Get bed assignment information with patient and bed details
$stmt = $pdo->prepare("
    SELECT ba.*, 
           b.bed_number, b.bed_type, b.price_per_day, b.ward_id,
           w.ward_name, w.floor_number,
           p.id as patient_db_id, p.patient_id, p.first_name, p.last_name, 
           p.date_of_birth, p.gender, p.phone, p.email,
           p.blood_type, p.emergency_contact_name, p.emergency_contact_phone,
           p.insurance_provider, p.insurance_id,
           u.first_name as assigned_by_first, u.last_name as assigned_by_last
    FROM bed_assignments ba 
    JOIN beds b ON ba.bed_id = b.id 
    JOIN ward w ON b.ward_id = w.id 
    JOIN patients p ON ba.patient_id = p.id
    LEFT JOIN users u ON ba.assigned_by = u.id
    WHERE ba.bed_id = ? AND ba.status = 'active'
");
$stmt->execute([$bed_id]);
$assignment = $stmt->fetch();

if (!$assignment) {
    $_SESSION['error'] = "No active bed assignment found for this bed.";
    header("Location: beds.php");
    exit;
}

// Get additional services that might be billed
$additional_services = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM services WHERE is_active = 1 ORDER BY category, name
    ");
    $stmt->execute();
    $additional_services = $stmt->fetchAll();
} catch (Exception $e) {
    // Continue without additional services if table doesn't exist
}

// Process discharge form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Get form data
        $discharge_date = $_POST['discharge_date'] ?? date('Y-m-d H:i:s');
        $discharge_notes = $_POST['discharge_notes'] ?? '';
        $final_diagnosis = $_POST['final_diagnosis'] ?? '';
        $treatment_summary = $_POST['treatment_summary'] ?? '';
        $follow_up_instructions = $_POST['follow_up_instructions'] ?? '';
        
        $bed_charges = isset($_POST['bed_charges']) ? floatval($_POST['bed_charges']) : 0;
        $additional_charges = isset($_POST['additional_charges']) ? floatval($_POST['additional_charges']) : 0;
        $discount = isset($_POST['discount']) ? floatval($_POST['discount']) : 0;
        $tax_amount = isset($_POST['tax_amount']) ? floatval($_POST['tax_amount']) : 0;
        $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;
        
        $payment_method = $_POST['payment_method'] ?? 'cash';
        $payment_reference = $_POST['payment_reference'] ?? '';
        $paid_amount = isset($_POST['paid_amount']) ? floatval($_POST['paid_amount']) : 0;
        
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
            WHERE id = ?
        ");
        $stmt->execute([
            $discharge_date,
            $discharge_notes,
            $final_diagnosis,
            $treatment_summary,
            $follow_up_instructions,
            $_SESSION['user_id'] ?? null,
            $assignment['id']
        ]);
        
        // Update bed status to available
        $stmt = $pdo->prepare("UPDATE beds SET bed_status = 'available' WHERE id = ?");
        $stmt->execute([$bed_id]);
        
        // Calculate stay duration for billing
        $admission_date = new DateTime($assignment['admission_date']);
        $discharge_date_obj = new DateTime($discharge_date);
        $days_stayed = $admission_date->diff($discharge_date_obj)->days;
        $days_stayed = max(1, $days_stayed); // Minimum 1 day
        
        // Generate bill number
        $bill_number = 'BL-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        // Create billing record
        $stmt = $pdo->prepare("
            INSERT INTO billing (
                patient_id, bill_number, total_amount, paid_amount, balance_amount,
                status, due_date, payment_method, payment_reference, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
       $bill_status = ($paid_amount >= $total_amount) ? 'paid' : 
              (($paid_amount > 0) ? 'partial' : 'pending');
        $balance_amount = $total_amount - $paid_amount;
        $due_date = date('Y-m-d', strtotime('+7 days'));
        
        $stmt->execute([
            $assignment['patient_db_id'],
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
        if ($additional_charges > 0 && isset($_POST['service_items'])) {
            foreach ($_POST['service_items'] as $service) {
                if (!empty($service['description']) && $service['amount'] > 0) {
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
}

// Calculate default charges
$admission_date = new DateTime($assignment['admission_date']);
$current_date = new DateTime();
$days_stayed = $admission_date->diff($current_date)->days;
$days_stayed = max(1, $days_stayed);
$default_bed_charges = $days_stayed * $assignment['price_per_day'];
$default_total = $default_bed_charges;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discharge Patient - Hospital Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8fafc;
            color: #334155;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            color: #dc2626;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: #64748b;
            font-size: 1.1rem;
        }

        .card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            background: #fef2f2;
            padding: 1.5rem;
            border-bottom: 1px solid #fecaca;
        }

        .card-header h2 {
            color: #dc2626;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .patient-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .info-label {
            font-weight: 600;
            color: #475569;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-value {
            color: #1e293b;
            font-size: 1rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }

        .form-input, .form-select, .form-textarea {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .billing-section {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .billing-section h3 {
            color: #0369a1;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .bill-summary {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .bill-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #fed7aa;
        }

        .bill-item:last-child {
            border-bottom: none;
            font-weight: 600;
            font-size: 1.1rem;
            color: #dc2626;
        }

        .bill-total {
            font-weight: 700;
            font-size: 1.2rem;
            color: #dc2626;
        }

        .service-item {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 0.5rem;
            align-items: end;
            margin-bottom: 0.5rem;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #dc2626;
            color: white;
        }

        .btn-primary:hover {
            background: #b91c1c;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-outline {
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-outline:hover {
            background: #f9fafb;
        }

        .btn-success {
            background: #059669;
            color: white;
        }

        .btn-success:hover {
            background: #047857;
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-warning {
            background: #fffbeb;
            border: 1px solid #fcd34d;
            color: #92400e;
        }

        .add-service-btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .remove-service {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 0.375rem;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .patient-info {
                grid-template-columns: 1fr;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
            }

            .service-item {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-user-minus"></i> Discharge Patient</h1>
            <p>Complete discharge process and generate final bill</p>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($_SESSION['error']); ?>
                <?php unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="process_discharge.php" id="dischargeForm">
        <input type="hidden" name="bed_id" value="<?php echo $bed_id; ?>">   
        <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-user-injured"></i> Patient & Admission Information</h2>
                </div>
                <div class="card-body">
                    <div class="patient-info">
                        <div class="info-group">
                            <span class="info-label">Patient Name</span>
                            <span class="info-value"><?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Patient ID</span>
                            <span class="info-value"><?php echo htmlspecialchars($assignment['patient_id']); ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Date of Birth</span>
                            <span class="info-value"><?php echo date('M j, Y', strtotime($assignment['date_of_birth'])); ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Gender</span>
                            <span class="info-value"><?php echo htmlspecialchars($assignment['gender']); ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Contact Number</span>
                            <span class="info-value"><?php echo htmlspecialchars($assignment['phone']); ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Insurance</span>
                            <span class="info-value"><?php echo htmlspecialchars($assignment['insurance_provider'] ?? 'None'); ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Admission Date</span>
                            <span class="info-value"><?php echo date('M j, Y g:i A', strtotime($assignment['admission_date'])); ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Bed Number</span>
                            <span class="info-value"><?php echo htmlspecialchars($assignment['bed_number']); ?> (<?php echo htmlspecialchars($assignment['bed_type']); ?>)</span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Ward</span>
                            <span class="info-value"><?php echo htmlspecialchars($assignment['ward_name']); ?> (Floor <?php echo $assignment['floor_number']; ?>)</span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Daily Rate</span>
                            <span class="info-value">Ksh <?php echo number_format($assignment['price_per_day'], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-file-medical"></i> Medical Discharge Information</h2>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="discharge_date">Discharge Date & Time *</label>
                            <input type="datetime-local" 
                                   class="form-input" 
                                   id="discharge_date" 
                                   name="discharge_date" 
                                   value="<?php echo date('Y-m-d\TH:i'); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="final_diagnosis">Final Diagnosis</label>
                            <input type="text" 
                                   class="form-input" 
                                   id="final_diagnosis" 
                                   name="final_diagnosis" 
                                   placeholder="Enter final diagnosis">
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="treatment_summary">Treatment Summary</label>
                            <textarea class="form-textarea" 
                                      id="treatment_summary" 
                                      name="treatment_summary" 
                                      placeholder="Summary of treatments provided during stay"></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="follow_up_instructions">Follow-up Instructions</label>
                            <textarea class="form-textarea" 
                                      id="follow_up_instructions" 
                                      name="follow_up_instructions" 
                                      placeholder="Instructions for patient after discharge"></textarea>
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label" for="discharge_notes">Discharge Notes</label>
                            <textarea class="form-textarea" 
                                      id="discharge_notes" 
                                      name="discharge_notes" 
                                      placeholder="Additional notes about the discharge"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-receipt"></i> Billing & Payment Information</h2>
                </div>
                <div class="card-body">
                    <div class="billing-section">
                        <h3><i class="fas fa-bed"></i> Bed Charges</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Days Stayed</label>
                                <input type="number" class="form-input" id="days_stayed" value="<?php echo $days_stayed; ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Daily Rate</label>
                                <input type="number" class="form-input" id="daily_rate" value="<?php echo $assignment['price_per_day']; ?>" step="0.01" readonly>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Bed Charges Total</label>
                                <input type="number" class="form-input" id="bed_charges" name="bed_charges" value="<?php echo $default_bed_charges; ?>" step="0.01" readonly>
                            </div>
                        </div>
                    </div>

                    <div class="billing-section">
                        <h3><i class="fas fa-plus-circle"></i> Additional Services</h3>
                        <button type="button" class="add-service-btn" onclick="addServiceItem()">
                            <i class="fas fa-plus"></i> Add Service
                        </button>
                        
                        <div id="serviceItems">
                            <!-- Service items will be added here dynamically -->
                        </div>
                    </div>

                    <div class="bill-summary">
                        <h3><i class="fas fa-calculator"></i> Bill Summary</h3>
                        <div class="bill-item">
                            <span>Bed Charges:</span>
                            <span id="bedChargesDisplay">Ksh <?php echo number_format($default_bed_charges, 2); ?></span>
                        </div>
                        <div class="bill-item">
                            <span>Additional Charges:</span>
                            <span id="additionalChargesDisplay">Ksh 0.00</span>
                        </div>
                        <div class="bill-item">
                            <span>Subtotal:</span>
                            <span id="subtotalDisplay">Ksh <?php echo number_format($default_bed_charges, 2); ?></span>
                        </div>
                        <div class="bill-item">
                            <span>Discount:</span>
                            <span id="discountDisplay">Ksh 0.00</span>
                        </div>
                        <div class="bill-item">
                            <span>Tax:</span>
                            <span id="taxDisplay">Ksh 0.00</span>
                        </div>
                        <div class="bill-item bill-total">
                            <span>Total Amount:</span>
                            <span id="totalAmountDisplay">Ksh <?php echo number_format($default_total, 2); ?></span>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Discount Amount</label>
                            <input type="number" class="form-input" id="discount" name="discount" value="0" step="0.01" min="0" onchange="calculateTotal()">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tax Amount</label>
                            <input type="number" class="form-input" id="tax_amount" name="tax_amount" value="0" step="0.01" min="0" onchange="calculateTotal()">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Total Amount</label>
                            <input type="number" class="form-input" id="total_amount" name="total_amount" value="<?php echo $default_total; ?>" step="0.01" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Payment Method *</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="cash">Cash</option>
                                <option value="mpesa">M-Pesa</option>
                                <option value="card">Credit/Debit Card</option>
                                <option value="insurance">Insurance</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Payment Reference</label>
                            <input type="text" class="form-input" id="payment_reference" name="payment_reference" placeholder="Reference number if any">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount Paid *</label>
                            <input type="number" class="form-input" id="paid_amount" name="paid_amount" value="<?php echo $default_total; ?>" step="0.01" min="0" max="<?php echo $default_total; ?>" required onchange="calculateTotal()">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Balance Due</label>
                            <input type="number" class="form-input" id="balance_due" value="0" step="0.01" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="button-group">
                <a href="beds.php?ward_id=<?php echo $assignment['ward_id']; ?>" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Beds
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check-circle"></i> Complete Discharge & Generate Bill
                </button>
            </div>
        </form>
    </div>

    <script>
        let serviceCount = 0;

        // Set default discharge date to current datetime
        document.getElementById('discharge_date').value = new Date().toISOString().slice(0, 16);
        
        function addServiceItem(description = '', amount = 0, quantity = 1, type = 'other') {
            serviceCount++;
            const serviceItems = document.getElementById('serviceItems');
            const serviceDiv = document.createElement('div');
            serviceDiv.className = 'service-item';
            serviceDiv.innerHTML = `
                <input type="text" class="form-input" name="service_items[${serviceCount}][description]" 
                       placeholder="Service description" value="${description}" onchange="calculateTotal()">
                <input type="number" class="form-input" name="service_items[${serviceCount}][quantity]" 
                       value="${quantity}" min="1" placeholder="Qty" onchange="calculateTotal()">
                <input type="number" class="form-input" name="service_items[${serviceCount}][amount]" 
                       value="${amount}" step="0.01" min="0" placeholder="Amount" onchange="calculateTotal()">
                <select class="form-select" name="service_items[${serviceCount}][type]" onchange="calculateTotal()">
                    <option value="consultation" ${type === 'consultation' ? 'selected' : ''}>Consultation</option>
                    <option value="lab_test" ${type === 'lab_test' ? 'selected' : ''}>Lab Test</option>
                    <option value="medication" ${type === 'medication' ? 'selected' : ''}>Medication</option>
                    <option value="procedure" ${type === 'procedure' ? 'selected' : ''}>Procedure</option>
                    <option value="other" ${type === 'other' ? 'selected' : ''}>Other</option>
                </select>
                <button type="button" class="remove-service" onclick="this.parentElement.remove(); calculateTotal();">
                    <i class="fas fa-times"></i>
                </button>
            `;
            serviceItems.appendChild(serviceDiv);
        }

        function calculateTotal() {
            // Calculate bed charges
            const daysStayed = parseInt(document.getElementById('days_stayed').value) || 0;
            const dailyRate = parseFloat(document.getElementById('daily_rate').value) || 0;
            const bedCharges = daysStayed * dailyRate;
            document.getElementById('bed_charges').value = bedCharges.toFixed(2);
            document.getElementById('bedChargesDisplay').textContent = `Ksh ${bedCharges.toFixed(2)}`;

            // Calculate additional charges
            let additionalCharges = 0;
            const serviceInputs = document.querySelectorAll('input[name^="service_items"]');
            for (let i = 0; i < serviceInputs.length; i++) {
                if (serviceInputs[i].name.includes('[amount]')) {
                    additionalCharges += parseFloat(serviceInputs[i].value) || 0;
                }
            }
            document.getElementById('additionalChargesDisplay').textContent = `Ksh ${additionalCharges.toFixed(2)}`;

            // Calculate subtotal
            const subtotal = bedCharges + additionalCharges;
            document.getElementById('subtotalDisplay').textContent = `Ksh ${subtotal.toFixed(2)}`;

            // Apply discount and tax
            const discount = parseFloat(document.getElementById('discount').value) || 0;
            const tax = parseFloat(document.getElementById('tax_amount').value) || 0;
            document.getElementById('discountDisplay').textContent = `Ksh ${discount.toFixed(2)}`;
            document.getElementById('taxDisplay').textContent = `Ksh ${tax.toFixed(2)}`;

            // Calculate total
            const total = subtotal - discount + tax;
            document.getElementById('total_amount').value = total.toFixed(2);
            document.getElementById('totalAmountDisplay').textContent = `Ksh ${total.toFixed(2)}`;

            // Calculate balance
            const paidAmount = parseFloat(document.getElementById('paid_amount').value) || 0;
            const balance = total - paidAmount;
            document.getElementById('balance_due').value = balance.toFixed(2);

            // Update paid amount max value
            document.getElementById('paid_amount').max = total;
        }

        // Add some common services on page load
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotal();
            
            // Add common services if available
            <?php if (!empty($additional_services)): ?>
                <?php foreach($additional_services as $service): ?>
                    addServiceItem(
                        '<?php echo addslashes($service['name']); ?> - <?php echo addslashes($service['category']); ?>',
                        <?php echo $service['price']; ?>,
                        1,
                        'other'
                    );
                <?php endforeach; ?>
                calculateTotal();
            <?php endif; ?>
        });

        // Confirm before discharge
        document.getElementById('dischargeForm').addEventListener('submit', function(e) {
            const totalAmount = parseFloat(document.getElementById('total_amount').value) || 0;
            const paidAmount = parseFloat(document.getElementById('paid_amount').value) || 0;
            
            if (paidAmount < totalAmount) {
                const balance = totalAmount - paidAmount;
                if (!confirm(`Patient will be discharged with a balance of Ksh ${balance.toFixed(2)}. Continue?`)) {
                    e.preventDefault();
                }
            } else {
                if (!confirm('Are you sure you want to discharge this patient and generate the final bill?')) {
                    e.preventDefault();
                }
            }
        });

        // Recalculate when discharge date changes
        document.getElementById('discharge_date').addEventListener('change', function() {
            const admissionDate = new Date('<?php echo $assignment['admission_date']; ?>');
            const dischargeDate = new Date(this.value);
            const diffTime = Math.abs(dischargeDate - admissionDate);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            const daysStayed = Math.max(1, diffDays);
            
            document.getElementById('days_stayed').value = daysStayed;
            calculateTotal();
        });
    </script>
</body>
</html>