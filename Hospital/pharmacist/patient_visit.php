<?php
session_start();
include "includes/db_connect.php";

$visit_id = $_GET['visit_id'] ?? null;
$patient_visit = null;
$patient_data = null;
$visit_components = [];
$clinical_notes_data = [];

// Function implementations
function updateTriage($pdo, $visit_id, $data) {
    try {
        // Update patient vitals
        $vitals_stmt = $pdo->prepare("
            INSERT INTO patient_vitals 
            (patient_id, blood_pressure_systolic, blood_pressure_diastolic, heart_rate, 
             temperature, oxygen_saturation, recorded_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $visit_stmt = $pdo->prepare("SELECT patient_id FROM patient_visits WHERE id = ?");
        $visit_stmt->execute([$visit_id]);
        $visit = $visit_stmt->fetch();
        
        $vitals_stmt->execute([
            $visit['patient_id'],
            $data['bp_systolic'] ?? null,
            $data['bp_diastolic'] ?? null,
            $data['heart_rate'] ?? null,
            $data['temperature'] ?? null,
            $data['oxygen_saturation'] ?? null,
            $_SESSION['user_id']
        ]);
        
        // Update visit status and triage info
        $update_visit = $pdo->prepare("
            UPDATE patient_visits 
            SET chief_complaint = ?, visit_status = 'doctor_assigned', triage_nurse_id = ?
            WHERE id = ?
        ");
        $update_visit->execute([
            $data['chief_complaint'],
            $_SESSION['user_id'],
            $visit_id
        ]);
        
        // Save component
        $component_stmt = $pdo->prepare("
            INSERT INTO visit_components 
            (visit_id, component_type, component_data, created_by) 
            VALUES (?, 'triage', ?, ?)
        ");
        
        $component_data = json_encode([
            'chief_complaint' => $data['chief_complaint'],
            'bp' => ($data['bp_systolic'] ?? '') . '/' . ($data['bp_diastolic'] ?? ''),
            'heart_rate' => $data['heart_rate'] ?? null,
            'temperature' => $data['temperature'] ?? null,
            'oxygen_saturation' => $data['oxygen_saturation'] ?? null,
            'summary' => 'Triage completed: ' . substr($data['chief_complaint'], 0, 50) . '...'
        ]);
        
        $component_stmt->execute([$visit_id, $component_data, $_SESSION['user_id']]);
        
        return true;
    } catch (Exception $e) {
        throw new Exception('Triage update failed: ' . $e->getMessage());
    }
}

function saveClinicalNotes($pdo, $visit_id, $data) {
    try {
        $component_stmt = $pdo->prepare("
            INSERT INTO visit_components 
            (visit_id, component_type, component_data, created_by) 
            VALUES (?, 'clinical_notes', ?, ?)
        ");
        
        $component_data = json_encode([
            'subjective' => $data['subjective'] ?? '',
            'objective' => $data['objective'] ?? '',
            'assessment' => $data['assessment'] ?? '',
            'plan' => $data['plan'] ?? '',
            'summary' => 'Clinical notes updated'
        ]);
        
        $component_stmt->execute([$visit_id, $component_data, $_SESSION['user_id']]);
        
        // Update visit status based on content
        if (!empty($data['assessment']) && !empty($data['plan'])) {
            // If both assessment and plan are filled, move to treatment
            $update_stmt = $pdo->prepare("UPDATE patient_visits SET visit_status = 'treatment' WHERE id = ?");
            $update_stmt->execute([$visit_id]);
        } elseif (!empty($data['assessment']) && empty($data['plan'])) {
            // If only assessment is filled, move to lab ordering
            $update_stmt = $pdo->prepare("UPDATE patient_visits SET visit_status = 'awaiting_labs' WHERE id = ?");
            $update_stmt->execute([$visit_id]);
        } else {
            // If only subjective/objective, stay in progress
            $update_stmt = $pdo->prepare("UPDATE patient_visits SET visit_status = 'in_progress' WHERE id = ?");
            $update_stmt->execute([$visit_id]);
        }
        
        return true;
    } catch (Exception $e) {
        throw new Exception('Clinical notes save failed: ' . $e->getMessage());
    }
}

function orderLabs($pdo, $visit_id, $data) {
    try {
        // Create lab order with visit_id
        $lab_order_stmt = $pdo->prepare("
            INSERT INTO lab_orders 
            (patient_id, ordered_by, priority, clinical_notes, status, visit_id) 
            VALUES (?, ?, ?, ?, 'ordered', ?)
        ");
        
        $visit_stmt = $pdo->prepare("SELECT patient_id FROM patient_visits WHERE id = ?");
        $visit_stmt->execute([$visit_id]);
        $visit = $visit_stmt->fetch();
        
        $lab_order_stmt->execute([
            $visit['patient_id'],
            $_SESSION['user_id'],
            $data['priority'],
            $data['instructions'],
            $visit_id
        ]);
        
        $lab_order_id = $pdo->lastInsertId();

        // Add lab order items
        $lab_item_stmt = $pdo->prepare("
            INSERT INTO lab_order_items 
            (lab_order_id, test_name, specimen_type, instructions, status) 
            VALUES (?, ?, ?, ?, 'ordered')
        ");
        
        $test_name = $data['test_type'];
        $test_names = [
            'cbc' => 'Complete Blood Count',
            'bmp' => 'Basic Metabolic Panel',
            'lft' => 'Liver Function Test',
            'urinalysis' => 'Urinalysis',
            'thyroid' => 'Thyroid Function Test',
            'other' => 'Other Test'
        ];
        
        $lab_item_stmt->execute([
            $lab_order_id,
            $test_names[$test_name] ?? $test_name,
            'Blood',
            $data['instructions']
        ]);

        // Save component
        $component_stmt = $pdo->prepare("
            INSERT INTO visit_components 
            (visit_id, component_type, component_data, created_by) 
            VALUES (?, 'lab_order', ?, ?)
        ");
        
        $component_data = json_encode([
            'test_type' => $test_names[$test_name] ?? $test_name,
            'priority' => $data['priority'],
            'instructions' => $data['instructions'],
            'lab_order_id' => $lab_order_id,
            'summary' => 'Lab ordered: ' . ($test_names[$test_name] ?? $test_name)
        ]);
        
        $component_stmt->execute([$visit_id, $component_data, $_SESSION['user_id']]);

        // Update visit status to await lab results
        $update_stmt = $pdo->prepare("UPDATE patient_visits SET visit_status = 'awaiting_lab_results' WHERE id = ?");
        $update_stmt->execute([$visit_id]);
        
        return true;
    } catch (Exception $e) {
        throw new Exception('Lab order failed: ' . $e->getMessage());
    }
}

function updateLabResults($pdo, $visit_id, $data) {
    try {
        // Update lab results status
        $update_stmt = $pdo->prepare("
            UPDATE lab_orders 
            SET status = 'completed', completed_at = NOW() 
            WHERE id = ?
        ");
        $update_stmt->execute([$data['lab_order_id']]);
        
        // Update lab order items with results
        if (isset($data['results']) && is_array($data['results'])) {
            foreach ($data['results'] as $result) {
                $result_stmt = $pdo->prepare("
                    UPDATE lab_order_items 
                    SET result_value = ?, result_unit = ?, reference_range = ?, 
                        result_notes = ?, status = 'completed'
                    WHERE id = ?
                ");
                $result_stmt->execute([
                    $result['value'],
                    $result['unit'],
                    $result['range'],
                    $result['notes'],
                    $result['item_id']
                ]);
            }
        }
        
        // Save component
        $component_stmt = $pdo->prepare("
            INSERT INTO visit_components 
            (visit_id, component_type, component_data, created_by) 
            VALUES (?, 'lab_results', ?, ?)
        ");
        
        $component_data = json_encode([
            'lab_order_id' => $data['lab_order_id'],
            'summary' => 'Lab results received and reviewed'
        ]);
        
        $component_stmt->execute([$visit_id, $component_data, $_SESSION['user_id']]);
        
        // Update visit status back to in_progress for final assessment
        $update_visit = $pdo->prepare("UPDATE patient_visits SET visit_status = 'in_progress' WHERE id = ?");
        $update_visit->execute([$visit_id]);
        
        return true;
    } catch (Exception $e) {
        throw new Exception('Lab results update failed: ' . $e->getMessage());
    }
}

function prescribeMedication($pdo, $visit_id, $data) {
    try {
        $med_stmt = $pdo->prepare("
            INSERT INTO patient_medications 
            (patient_id, medication_name, dosage, frequency, route, duration, 
             instructions, prescribing_doctor, status, start_date) 
            VALUES (?, ?, ?, ?, 'oral', ?, ?, ?, 'active', CURDATE())
        ");
        
        $visit_stmt = $pdo->prepare("SELECT patient_id FROM patient_visits WHERE id = ?");
        $visit_stmt->execute([$visit_id]);
        $visit = $visit_stmt->fetch();
        
        $med_stmt->execute([
            $visit['patient_id'],
            $data['medication_name'],
            $data['dosage'],
            $data['frequency'],
            $data['duration'],
            $data['instructions'] ?? '',
            $_SESSION['user_id']
        ]);
        
        // Save component
        $component_stmt = $pdo->prepare("
            INSERT INTO visit_components 
            (visit_id, component_type, component_data, created_by) 
            VALUES (?, 'medication', ?, ?)
        ");
        
        $component_data = json_encode([
            'medication_name' => $data['medication_name'],
            'dosage' => $data['dosage'],
            'frequency' => $data['frequency'],
            'duration' => $data['duration'],
            'instructions' => $data['instructions'] ?? '',
            'summary' => 'Medication prescribed: ' . $data['medication_name']
        ]);
        
        $component_stmt->execute([$visit_id, $component_data, $_SESSION['user_id']]);
        
        // Update visit status to treatment if not already
        $check_stmt = $pdo->prepare("SELECT visit_status FROM patient_visits WHERE id = ?");
        $check_stmt->execute([$visit_id]);
        $current_status = $check_stmt->fetchColumn();
        
        if ($current_status !== 'treatment') {
            $update_stmt = $pdo->prepare("UPDATE patient_visits SET visit_status = 'treatment' WHERE id = ?");
            $update_stmt->execute([$visit_id]);
        }
        
        return true;
    } catch (Exception $e) {
        throw new Exception('Medication prescription failed: ' . $e->getMessage());
    }
}

function updateDiagnosis($pdo, $visit_id, $data) {
    try {
        $update_stmt = $pdo->prepare("
            UPDATE patient_visits 
            SET final_diagnosis = ?, treatment_summary = ?, visit_status = 'treatment'
            WHERE id = ?
        ");
        
        $update_stmt->execute([
            $data['final_diagnosis'],
            $data['treatment_summary'],
            $visit_id
        ]);
        
        // Save component
        $component_stmt = $pdo->prepare("
            INSERT INTO visit_components 
            (visit_id, component_type, component_data, created_by) 
            VALUES (?, 'diagnosis', ?, ?)
        ");
        
        $component_data = json_encode([
            'final_diagnosis' => $data['final_diagnosis'],
            'treatment_summary' => $data['treatment_summary'],
            'summary' => 'Diagnosis updated: ' . substr($data['final_diagnosis'], 0, 50) . '...'
        ]);
        
        $component_stmt->execute([$visit_id, $component_data, $_SESSION['user_id']]);
        
        return true;
    } catch (Exception $e) {
        throw new Exception('Diagnosis update failed: ' . $e->getMessage());
    }
}

function dischargePatient($pdo, $visit_id, $data) {
    try {
        $update_stmt = $pdo->prepare("
            UPDATE patient_visits 
            SET final_diagnosis = ?, treatment_summary = ?, discharge_instructions = ?,
                follow_up_date = ?, visit_status = 'discharged'
            WHERE id = ?
        ");
        
        $update_stmt->execute([
            $data['final_diagnosis'],
            $data['treatment_summary'] ?? '',
            $data['discharge_instructions'],
            $data['follow_up_date'] ?? null,
            $visit_id
        ]);
        
        // Create billing record
        createBillingRecord($pdo, $visit_id);
        
        // Save component
        $component_stmt = $pdo->prepare("
            INSERT INTO visit_components 
            (visit_id, component_type, component_data, created_by) 
            VALUES (?, 'discharge', ?, ?)
        ");
        
        $component_data = json_encode([
            'final_diagnosis' => $data['final_diagnosis'],
            'discharge_instructions' => $data['discharge_instructions'],
            'follow_up_date' => $data['follow_up_date'] ?? null,
            'summary' => 'Patient discharged with follow-up instructions'
        ]);
        
        $component_stmt->execute([$visit_id, $component_data, $_SESSION['user_id']]);
        
        return true;
    } catch (Exception $e) {
        throw new Exception('Discharge failed: ' . $e->getMessage());
    }
}

function createBillingRecord($pdo, $visit_id) {
    try {
        // Get visit information for billing
        $visit_stmt = $pdo->prepare("
            SELECT pv.*, p.patient_id, p.insurance_provider, p.insurance_id
            FROM patient_visits pv
            JOIN patients p ON pv.patient_id = p.id
            WHERE pv.id = ?
        ");
        $visit_stmt->execute([$visit_id]);
        $visit = $visit_stmt->fetch();
        
        // Calculate charges based on services provided
        $total_amount = calculateVisitCharges($pdo, $visit_id);
        
        // Create billing record
        $billing_stmt = $pdo->prepare("
            INSERT INTO billing 
            (patient_id, bill_number, bill_date, total_amount, balance_amount, 
             status, due_date, payment_method, insurance_provider, insurance_id, created_by) 
            VALUES (?, ?, NOW(), ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 30 DAY), 
                   'insurance', ?, ?, ?)
        ");
        
        $bill_number = 'BL-' . date('Ymd') . '-' . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
        
        $billing_stmt->execute([
            $visit['patient_id'],
            $bill_number,
            $total_amount,
            $total_amount,
            $visit['insurance_provider'],
            $visit['insurance_id'],
            $_SESSION['user_id']
        ]);
        
        $bill_id = $pdo->lastInsertId();
        
        // Add bill items
        addBillItems($pdo, $bill_id, $visit_id, $total_amount);
        
        return true;
    } catch (Exception $e) {
        // Log error but don't prevent discharge
        error_log('Billing creation failed: ' . $e->getMessage());
        return false;
    }
}

function calculateVisitCharges($pdo, $visit_id) {
    // Base consultation fee
    $total = 500.00;
    
    // Add lab charges
    $labs_stmt = $pdo->prepare("
        SELECT COUNT(*) as lab_count 
        FROM visit_components 
        WHERE visit_id = ? AND component_type = 'lab_order'
    ");
    $labs_stmt->execute([$visit_id]);
    $labs = $labs_stmt->fetch();
    $total += $labs['lab_count'] * 800.00;
    
    // Add medication charges
    $meds_stmt = $pdo->prepare("
        SELECT COUNT(*) as med_count 
        FROM visit_components 
        WHERE visit_id = ? AND component_type = 'medication'
    ");
    $meds_stmt->execute([$visit_id]);
    $meds = $meds_stmt->fetch();
    $total += $meds['med_count'] * 200.00;
    
    return $total;
}

function addBillItems($pdo, $bill_id, $visit_id, $total_amount) {
    $items = [
        ['description' => 'Consultation Fee', 'amount' => 500.00, 'type' => 'consultation'],
        ['description' => 'Laboratory Services', 'amount' => 800.00, 'type' => 'lab_test'],
        ['description' => 'Pharmacy', 'amount' => 200.00, 'type' => 'medication']
    ];
    
    foreach ($items as $item) {
        $item_stmt = $pdo->prepare("
            INSERT INTO bill_items 
            (bill_id, item_description, quantity, unit_price, total_amount, item_type) 
            VALUES (?, ?, 1, ?, ?, ?)
        ");
        
        $item_stmt->execute([
            $bill_id,
            $item['description'],
            $item['amount'],
            $item['amount'],
            $item['type']
        ]);
    }
}

// Helper function to get latest clinical notes
function getLatestClinicalNotes($visit_components) {
    $latest_notes = ['subjective' => '', 'objective' => '', 'assessment' => '', 'plan' => ''];
    
    foreach (array_reverse($visit_components) as $component) {
        if ($component['component_type'] === 'clinical_notes') {
            $data = json_decode($component['component_data'], true);
            foreach (['subjective', 'objective', 'assessment', 'plan'] as $field) {
                if (!empty($data[$field]) && empty($latest_notes[$field])) {
                    $latest_notes[$field] = $data[$field];
                }
            }
        }
    }
    
    return $latest_notes;
}

// Helper function to check if lab results are available
function hasPendingLabResults($pdo, $visit_id) {
    $stmt = $pdo->prepare("
        SELECT lo.id 
        FROM lab_orders lo
        JOIN visit_components vc ON vc.component_data LIKE CONCAT('%\"lab_order_id\":', lo.id, '%')
        WHERE vc.visit_id = ? AND lo.status = 'ordered'
    ");
    $stmt->execute([$visit_id]);
    return $stmt->fetch() !== false;
}

if ($visit_id) {
    // Get visit data
    $visit_stmt = $pdo->prepare("
        SELECT pv.*, 
               p.*,
               CONCAT(p.first_name, ' ', p.last_name) as patient_full_name,
               CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
               CONCAT(n.first_name, ' ', n.last_name) as nurse_name
        FROM patient_visits pv
        JOIN patients p ON pv.patient_id = p.id
        LEFT JOIN users d ON pv.assigned_doctor_id = d.id
        LEFT JOIN users n ON pv.triage_nurse_id = n.id
        WHERE pv.id = ?
    ");
    $visit_stmt->execute([$visit_id]);
    $patient_visit = $visit_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($patient_visit) {
        $patient_data = $patient_visit;
        
        // Get visit components
        $components_stmt = $pdo->prepare("
            SELECT vc.*, CONCAT(u.first_name, ' ', u.last_name) as staff_name
            FROM visit_components vc
            LEFT JOIN users u ON vc.created_by = u.id
            WHERE vc.visit_id = ? 
            ORDER BY vc.created_at ASC
        ");
        $components_stmt->execute([$visit_id]);
        $visit_components = $components_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get latest clinical notes for form population
        $clinical_notes_data = getLatestClinicalNotes($visit_components);
        
        // Check for pending lab results
        $has_pending_labs = hasPendingLabResults($pdo, $visit_id);
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $patient_visit) {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_triage':
                updateTriage($pdo, $visit_id, $_POST);
                break;
                
            case 'save_clinical_notes':
                saveClinicalNotes($pdo, $visit_id, $_POST);
                break;
                
            case 'order_labs':
                orderLabs($pdo, $visit_id, $_POST);
                break;
                
            case 'update_lab_results':
                updateLabResults($pdo, $visit_id, $_POST);
                break;
                
            case 'prescribe_medication':
                prescribeMedication($pdo, $visit_id, $_POST);
                break;
                
            case 'update_diagnosis':
                updateDiagnosis($pdo, $visit_id, $_POST);
                break;
                
            case 'discharge_patient':
                dischargePatient($pdo, $visit_id, $_POST);
                break;
        }
        
        $_SESSION['success'] = "Action completed successfully!";
        header("Location: patient_visit.php?visit_id=" . $visit_id);
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: patient_visit.php?visit_id=" . $visit_id);
        exit();
    }
}

// Handle success/error messages
$success_message = $_SESSION['success'] ?? '';
$error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

include "includes/sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Visit - MediCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .visit-header {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .patient-banner {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .patient-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .visit-progress {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-top: 1px solid #e2e8f0;
        }
        
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            position: relative;
        }
        
        .progress-step::before {
            content: '';
            position: absolute;
            top: 20px;
            left: -50%;
            right: 50%;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }
        
        .progress-step:first-child::before {
            display: none;
        }
        
        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
            z-index: 2;
            position: relative;
        }
        
        .step-active .step-icon {
            background: #3b82f6;
            color: white;
        }
        
        .step-completed .step-icon {
            background: #10b981;
            color: white;
        }
        
        .visit-content {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 2rem;
        }
        
        .visit-forms {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .visit-sidebar {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            height: fit-content;
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .section-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .component-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .component-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-ordered { background: #fef3c7; color: #92400e; }
        .status-completed { background: #d1fae5; color: #065f46; }
    </style>
</head>
<body>
    <div class="main-content">
        <?php if (!$patient_visit): ?>
            <div style="text-align: center; padding: 4rem;">
                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #f59e0b; margin-bottom: 1rem;"></i>
                <h2>Visit Not Found</h2>
                <p>The requested patient visit could not be found.</p>
                <a href="ehr_dashboard.php" class="btn btn-primary">Return to Dashboard</a>
            </div>
        <?php else: ?>
            <!-- Display Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Visit Header -->
            <div class="visit-header">
                <div class="patient-banner">
                    <div class="patient-avatar">
                        <?php echo strtoupper(substr($patient_data['patient_full_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <h1 style="font-size: 1.5rem; font-weight: 700; color: #1e293b; margin-bottom: 0.25rem;">
                            <?php echo htmlspecialchars($patient_data['patient_full_name']); ?>
                        </h1>
                        <div style="color: #64748b;">
                            ID: <?php echo htmlspecialchars($patient_data['patient_id']); ?> 
                            • <?php echo $patient_data['age']; ?> years 
                            • <?php echo $patient_data['gender']; ?>
                            • Visit: <?php echo htmlspecialchars($patient_data['visit_number']); ?>
                        </div>
                    </div>
                </div>
                
                <div class="visit-progress">
                    <?php
                    $steps = [
                        'triage' => ['icon' => 'fa-user-nurse', 'label' => 'Triage'],
                        'doctor_assigned' => ['icon' => 'fa-user-md', 'label' => 'Doctor Review'],
                        'in_progress' => ['icon' => 'fa-stethoscope', 'label' => 'Examination'],
                        'awaiting_labs' => ['icon' => 'fa-flask', 'label' => 'Order Labs'],
                        'awaiting_lab_results' => ['icon' => 'fa-vial', 'label' => 'Lab Results'],
                        'treatment' => ['icon' => 'fa-pills', 'label' => 'Treatment'],
                        'discharged' => ['icon' => 'fa-sign-out-alt', 'label' => 'Discharge']
                    ];
                    
                    $current_step_index = array_search($patient_visit['visit_status'], array_keys($steps));
                    ?>
                    
                    <?php foreach ($steps as $status => $step): ?>
                        <?php
                        $step_index = array_search($status, array_keys($steps));
                        $step_class = '';
                        if ($step_index < $current_step_index) {
                            $step_class = 'step-completed';
                        } elseif ($step_index == $current_step_index) {
                            $step_class = 'step-active';
                        }
                        ?>
                        <div class="progress-step <?php echo $step_class; ?>">
                            <div class="step-icon">
                                <i class="fas <?php echo $step['icon']; ?>"></i>
                            </div>
                            <span style="font-size: 0.875rem; color: #64748b; text-align: center;">
                                <?php echo $step['label']; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="visit-content">
                <!-- Main Forms -->
                <div class="visit-forms">
                    <!-- Triage Section -->
                    <?php if (in_array($patient_visit['visit_status'], ['triage'])): ?>
                    <div class="form-section">
                        <div class="section-header">
                            <h2 style="font-size: 1.25rem; font-weight: 600; color: #1e293b;">Triage Assessment</h2>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="update_triage">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Chief Complaint</label>
                                    <textarea name="chief_complaint" class="form-textarea" placeholder="Describe the patient's main complaint..." required><?php echo htmlspecialchars($patient_visit['chief_complaint'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Triage Priority</label>
                                    <select name="triage_priority" class="form-select" required>
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                        <option value="critical">Critical</option>
                                    </select>
                                </div>
                            </div>
                            
                            <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">Vital Signs</h3>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Blood Pressure</label>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <input type="number" name="bp_systolic" class="form-input" placeholder="Systolic" min="50" max="250">
                                        <span style="align-self: center;">/</span>
                                        <input type="number" name="bp_diastolic" class="form-input" placeholder="Diastolic" min="30" max="150">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Heart Rate (BPM)</label>
                                    <input type="number" name="heart_rate" class="form-input" placeholder="72" min="30" max="200">
                                </div>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Temperature (°C)</label>
                                    <input type="number" name="temperature" class="form-input" placeholder="36.6" step="0.1" min="34" max="42">
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Oxygen Saturation (%)</label>
                                    <input type="number" name="oxygen_saturation" class="form-input" placeholder="98" min="70" max="100">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Complete Triage
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Clinical Notes Section - Available in multiple stages -->
                    <?php if (in_array($patient_visit['visit_status'], ['doctor_assigned', 'in_progress', 'awaiting_lab_results', 'treatment'])): ?>
                    <div class="form-section">
                        <div class="section-header">
                            <h2 style="font-size: 1.25rem; font-weight: 600; color: #1e293b;">
                                Clinical Notes
                                <?php if ($patient_visit['visit_status'] === 'awaiting_lab_results'): ?>
                                    <span class="status-badge status-ordered">Awaiting Lab Results</span>
                                <?php endif; ?>
                            </h2>
                        </div>
                        
                        <?php if ($patient_visit['visit_status'] === 'awaiting_lab_results'): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                Lab results are pending. You can update assessment and plan once results are available.
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="save_clinical_notes">
                            
                            <div class="form-group">
                                <label class="form-label">Subjective (Patient's Story)</label>
                                <textarea name="subjective" class="form-textarea" placeholder="Patient's complaints, symptoms, and history..."><?php echo htmlspecialchars($clinical_notes_data['subjective'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Objective (Findings)</label>
                                <textarea name="objective" class="form-textarea" placeholder="Physical exam findings, test results..."><?php echo htmlspecialchars($clinical_notes_data['objective'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Assessment (Diagnosis)</label>
                                <textarea name="assessment" class="form-textarea" placeholder="Working diagnosis, differentials..." 
                                          <?php echo $patient_visit['visit_status'] === 'awaiting_lab_results' ? 'readonly' : ''; ?>><?php echo htmlspecialchars($clinical_notes_data['assessment'] ?? ''); ?></textarea>
                                <?php if ($patient_visit['visit_status'] === 'awaiting_lab_results'): ?>
                                    <small style="color: #64748b;">Assessment will be editable after lab results are available</small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Plan (Treatment)</label>
                                <textarea name="plan" class="form-textarea" placeholder="Treatment plan, medications, follow-up..." 
                                          <?php echo $patient_visit['visit_status'] === 'awaiting_lab_results' ? 'readonly' : ''; ?>><?php echo htmlspecialchars($clinical_notes_data['plan'] ?? ''); ?></textarea>
                                <?php if ($patient_visit['visit_status'] === 'awaiting_lab_results'): ?>
                                    <small style="color: #64748b;">Treatment plan will be editable after lab results are available</small>
                                <?php endif; ?>
                            </div>
                            
                            <div style="display: flex; gap: 1rem;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Notes
                                </button>
                                
                                <?php if (empty($clinical_notes_data['assessment']) && $patient_visit['visit_status'] !== 'awaiting_lab_results'): ?>
                                    <button type="submit" name="request_labs" value="1" class="btn btn-warning">
                                        <i class="fas fa-flask"></i> Save & Request Labs
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Lab Orders Section -->
                    <?php if (in_array($patient_visit['visit_status'], ['in_progress', 'awaiting_labs'])): ?>
                    <div class="form-section">
                        <div class="section-header">
                            <h2 style="font-size: 1.25rem; font-weight: 600; color: #1e293b;">Laboratory Orders</h2>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="order_labs">
                            
                            <div class="form-group">
                                <label class="form-label">Test Type</label>
                                <select name="test_type" class="form-select" required>
                                    <option value="">Select test type</option>
                                    <option value="cbc">Complete Blood Count</option>
                                    <option value="bmp">Basic Metabolic Panel</option>
                                    <option value="lft">Liver Function Test</option>
                                    <option value="urinalysis">Urinalysis</option>
                                    <option value="thyroid">Thyroid Function</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Test Instructions</label>
                                <textarea name="instructions" class="form-textarea" placeholder="Specific instructions for the lab..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select">
                                    <option value="routine">Routine</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="stat">STAT</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-flask"></i> Order Lab Test
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Lab Results Section -->
                    <!-- Lab Results Section -->
<!-- Lab Results Section -->
<!-- Lab Results Section -->
<?php 
// FIXED: Enhanced lab results query - get all lab orders for this patient visit
$lab_results = [];
$pending_lab_orders = [];

if (isset($patient_visit['id'])) {
    $visit_id = $patient_visit['id']; // Use the actual visit ID from the current visit
    
    // First, let's debug what visit ID we're working with
    error_log("Checking lab data for visit ID: " . $visit_id);
    
    // OPTION 1: Check by patient_id (current working method)
    $patient_id = $patient_visit['patient_id'];
    
    // Get completed lab results for this patient
    $lab_stmt = $pdo->prepare("
        SELECT 
            loi.*, 
            lo.order_date, 
            lo.priority, 
            lo.id as lab_order_id,
            lo.status as order_status,
            u.first_name, 
            u.last_name,
            p.first_name as patient_first_name,
            p.last_name as patient_last_name
        FROM lab_order_items loi
        JOIN lab_orders lo ON loi.lab_order_id = lo.id
        JOIN users u ON lo.ordered_by = u.id
        JOIN patients p ON lo.patient_id = p.id
        WHERE lo.patient_id = ? 
        AND (loi.status = 'completed' OR loi.status = 'verified' OR loi.result_value IS NOT NULL)
        ORDER BY lo.order_date DESC, loi.test_name ASC
    ");
    $lab_stmt->execute([$patient_id]);
    $lab_results = $lab_stmt->fetchAll();
    
    // Get pending lab orders for this patient
    $pending_stmt = $pdo->prepare("
        SELECT 
            lo.*,
            loi.test_name,
            loi.specimen_type,
            u.first_name as doctor_first_name,
            u.last_name as doctor_last_name
        FROM lab_orders lo
        JOIN lab_order_items loi ON lo.id = loi.lab_order_id
        JOIN users u ON lo.ordered_by = u.id
        WHERE lo.patient_id = ? 
        AND lo.status IN ('ordered', 'sample_collected', 'in_progress', 'testing')
        AND (loi.result_value IS NULL OR loi.result_value = '')
        ORDER BY 
            CASE lo.priority 
                WHEN 'stat' THEN 1
                WHEN 'urgent' THEN 2
                WHEN 'routine' THEN 3
                ELSE 4
            END ASC,
            lo.order_date ASC
    ");
    $pending_stmt->execute([$patient_id]);
    $pending_lab_orders = $pending_stmt->fetchAll();
    
    // DEBUG: Log what we found
    error_log("Found " . count($lab_results) . " completed lab results for patient " . $patient_id);
    error_log("Found " . count($pending_lab_orders) . " pending lab orders for patient " . $patient_id);
    
    // OPTION 2: If you add visit_id to lab_orders table later, use this query:
    /*
    $lab_stmt = $pdo->prepare("
        SELECT 
            'order' as type,
            lo.id,
            lo.order_number,
            lo.order_date,
            lo.status,
            lo.priority,
            GROUP_CONCAT(loi.test_name) as test_names,
            loi.result_value,
            loi.result_unit,
            loi.reference_range,
            loi.result_notes,
            loi.tested_by,
            loi.tested_at
        FROM lab_orders lo
        LEFT JOIN lab_order_items loi ON lo.id = loi.lab_order_id
        WHERE lo.visit_id = ?
        GROUP BY lo.id
        
        UNION ALL
        
        SELECT 
            'result' as type,
            lr.id,
            lr.test_name as order_number,
            lr.performed_date as order_date,
            lr.status,
            'routine' as priority,
            lr.test_name as test_names,
            lr.result_value,
            lr.unit as result_unit,
            lr.reference_range,
            lr.technician_notes as result_notes,
            lr.recorded_by as tested_by,
            lr.result_date as tested_at
        FROM lab_results lr
        WHERE lr.record_id IN (SELECT id FROM medical_records WHERE visit_id = ?)
    ");
    $lab_stmt->execute([$visit_id, $visit_id]);
    $all_lab_data = $lab_stmt->fetchAll();
    */
}
?>

<!-- Completed Lab Results -->
<?php if (!empty($lab_results)): ?>
<div class="form-section">
    <div class="section-header">
        <h2 style="font-size: 1.25rem; font-weight: 600; color: #1e293b;">
            <i class="fas fa-flask"></i> Laboratory Results
            <span class="status-badge status-completed" style="margin-left: 0.5rem;">
                <?php echo count($lab_results); ?> Test(s) Completed
            </span>
        </h2>
    </div>
    
    <div style="background: #f0f9ff; border: 1px solid #bfdbfe; border-radius: 0.75rem; padding: 1.5rem;">
        <?php 
        $current_order_id = null;
        foreach ($lab_results as $result): 
            if ($current_order_id !== $result['lab_order_id']):
                $current_order_id = $result['lab_order_id'];
        ?>
            <div style="background: #dbeafe; padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong>Order #<?php echo htmlspecialchars($result['lab_order_id']); ?></strong>
                        <span style="color: #64748b; margin-left: 1rem;">
                            Ordered: <?php echo date('M j, Y g:i A', strtotime($result['order_date'])); ?>
                        </span>
                    </div>
                    <span class="priority-badge priority-<?php echo $result['priority']; ?>">
                        <?php echo strtoupper($result['priority']); ?>
                    </span>
                </div>
            </div>
        <?php endif; ?>
        
        <div style="margin-bottom: 1.5rem; padding: 1rem; background: white; border-radius: 0.5rem; border: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                <h4 style="font-weight: 600; color: #1e293b; margin: 0; font-size: 1.1rem;">
                    <?php echo htmlspecialchars($result['test_name']); ?>
                </h4>
                <span style="color: #10b981; font-size: 0.875rem; font-weight: 600;">
                    <i class="fas fa-check-circle"></i> Completed
                </span>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 0.75rem;">
                <div>
                    <strong style="color: #374151; font-size: 0.875rem;">Result:</strong>
                    <div style="color: #1e293b; font-weight: 600; font-size: 1.1rem; margin-top: 0.25rem;">
                        <?php echo htmlspecialchars($result['result_value']); ?>
                        <?php if ($result['result_unit']): ?>
                            <span style="color: #64748b; font-size: 0.875rem;">
                                <?php echo htmlspecialchars($result['result_unit']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($result['reference_range']): ?>
                <div>
                    <strong style="color: #374151; font-size: 0.875rem;">Reference Range:</strong>
                    <div style="color: #64748b; margin-top: 0.25rem;">
                        <?php echo htmlspecialchars($result['reference_range']); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div>
                    <strong style="color: #374151; font-size: 0.875rem;">Status:</strong>
                    <div style="margin-top: 0.25rem;">
                        <?php 
                        $result_value = floatval($result['result_value']);
                        $ref_range = $result['reference_range'];
                        
                        // Simple interpretation logic
                        if (preg_match('/(\d+\.?\d*)\s*-\s*(\d+\.?\d*)/', $ref_range, $matches)) {
                            $low = floatval($matches[1]);
                            $high = floatval($matches[2]);
                            if ($result_value < $low) {
                                echo '<span style="color: #f59e0b; font-weight: 600;">LOW</span>';
                            } elseif ($result_value > $high) {
                                echo '<span style="color: #dc2626; font-weight: 600;">HIGH</span>';
                            } else {
                                echo '<span style="color: #10b981; font-weight: 600;">NORMAL</span>';
                            }
                        } else {
                            echo '<span style="color: #64748b;">N/A</span>';
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <?php if ($result['result_notes']): ?>
            <div style="background: #f8fafc; padding: 0.75rem; border-radius: 0.375rem; margin-top: 0.5rem;">
                <strong style="color: #374151; font-size: 0.875rem;">Technician Notes:</strong>
                <p style="color: #64748b; margin: 0.25rem 0 0 0; font-size: 0.875rem;">
                    <?php echo htmlspecialchars($result['result_notes']); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <div style="color: #64748b; font-size: 0.75rem; margin-top: 0.75rem; padding-top: 0.5rem; border-top: 1px solid #e2e8f0;">
                Ordered by: Dr. <?php echo htmlspecialchars($result['first_name'] . ' ' . $result['last_name']); ?>
                <?php if ($result['tested_by']): ?>
                    • Tested by: <?php echo htmlspecialchars($result['tested_by']); ?>
                <?php endif; ?>
                <?php if ($result['tested_at']): ?>
                    on <?php echo date('M j, Y g:i A', strtotime($result['tested_at'])); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="alert alert-success" style="margin-top: 1rem;">
            <i class="fas fa-info-circle"></i> 
            <strong>Laboratory findings are available.</strong> Please incorporate these results into your clinical assessment and treatment plan.
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Rest of your existing code for pending labs and no data message -->

<!-- Pending Lab Orders -->
<?php if (!empty($pending_lab_orders)): ?>
<div class="form-section">
    <div class="section-header">
        <h2 style="font-size: 1.25rem; font-weight: 600; color: #1e293b;">
            <i class="fas fa-hourglass-half"></i> Pending Laboratory Tests
        </h2>
    </div>
    
    <div style="background: #fffbeb; border: 1px solid #fed7aa; border-radius: 0.75rem; padding: 1.5rem;">
        <?php foreach ($pending_lab_orders as $order): ?>
            <div style="margin-bottom: 1rem; padding: 1rem; background: white; border-radius: 0.5rem; border: 1px solid #e2e8f0;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                    <div>
                        <h4 style="font-weight: 600; color: #1e293b; margin: 0;">
                            <?php echo htmlspecialchars($order['test_name']); ?>
                        </h4>
                        <div style="color: #64748b; font-size: 0.875rem; margin-top: 0.25rem;">
                            Specimen: <?php echo htmlspecialchars($order['specimen_type']); ?>
                            • Ordered: <?php echo date('M j, Y', strtotime($order['order_date'])); ?>
                        </div>
                    </div>
                    <div style="display: flex; flex-direction: column; align-items: end; gap: 0.5rem;">
                        <span class="status-badge status-<?php echo $order['status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                        </span>
                        <span class="priority-badge priority-<?php echo $order['priority']; ?>">
                            <?php echo strtoupper($order['priority']); ?>
                        </span>
                    </div>
                </div>
                
                <div style="color: #64748b; font-size: 0.875rem;">
                    Ordered by: Dr. <?php echo htmlspecialchars($order['doctor_first_name'] . ' ' . $order['doctor_last_name']); ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="alert alert-info">
            <i class="fas fa-clock"></i> 
            Laboratory tests are currently being processed. Results will be available here upon completion.
        </div>
    </div>
</div>
<?php endif; ?>

<!-- No Lab Data Message -->
<?php if (empty($lab_results) && empty($pending_lab_orders)): ?>
<div class="form-section">
    <div class="section-header">
        <h2 style="font-size: 1.25rem; font-weight: 600; color: #1e293b;">
            <i class="fas fa-flask"></i> Laboratory Data
        </h2>
    </div>
    
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> 
        No laboratory tests have been ordered for this patient visit.
    </div>
</div>
<?php endif; ?>
                    <!-- Medications Section -->
                    <?php if (in_array($patient_visit['visit_status'], ['in_progress', 'treatment'])): ?>
                    <div class="form-section">
                        <div class="section-header">
                            <h2 style="font-size: 1.25rem; font-weight: 600; color: #1e293b;">Medications</h2>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="prescribe_medication">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Medication Name</label>
                                    <input type="text" name="medication_name" class="form-input" placeholder="e.g., Amoxicillin" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Dosage</label>
                                    <input type="text" name="dosage" class="form-input" placeholder="e.g., 500mg" required>
                                </div>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Frequency</label>
                                    <select name="frequency" class="form-select" required>
                                        <option value="once_daily">Once Daily</option>
                                        <option value="twice_daily">Twice Daily</option>
                                        <option value="three_times_daily">Three Times Daily</option>
                                        <option value="four_times_daily">Four Times Daily</option>
                                        <option value="as_needed">As Needed</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Duration</label>
                                    <input type="text" name="duration" class="form-input" placeholder="e.g., 7 days" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Instructions</label>
                                <textarea name="instructions" class="form-textarea" placeholder="Special instructions for the patient..."></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-prescription"></i> Prescribe Medication
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Discharge Section -->
                    <?php if (in_array($patient_visit['visit_status'], ['treatment'])): ?>
                    <div class="form-section">
                        <div class="section-header">
                            <h2 style="font-size: 1.25rem; font-weight: 600; color: #1e293b;">Discharge Patient</h2>
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="discharge_patient">
                            
                            <div class="form-group">
                                <label class="form-label">Final Diagnosis</label>
                                <textarea name="final_diagnosis" class="form-textarea" placeholder="Enter the final diagnosis..." required><?php echo htmlspecialchars($patient_visit['final_diagnosis'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Treatment Summary</label>
                                <textarea name="treatment_summary" class="form-textarea" placeholder="Summary of treatments provided..."><?php echo htmlspecialchars($patient_visit['treatment_summary'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Discharge Instructions</label>
                                <textarea name="discharge_instructions" class="form-textarea" placeholder="Instructions for the patient after discharge..." required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Follow-up Date</label>
                                <input type="date" name="follow_up_date" class="form-input" value="<?php echo $patient_visit['follow_up_date'] ?? ''; ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-sign-out-alt"></i> Discharge Patient
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Sidebar -->
                <div class="visit-sidebar">
                    <h3 style="font-size: 1.125rem; font-weight: 600; margin-bottom: 1rem;">Visit Timeline</h3>
                    
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($visit_components)): ?>
                            <div style="text-align: center; color: #64748b; padding: 1rem;">
                                <i class="fas fa-clock"></i>
                                <p>No activities recorded yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($visit_components as $component): ?>
                                <div class="component-card">
                                    <div class="component-header">
                                        <span style="font-weight: 600; color: #1e293b;">
                                            <?php echo str_replace('_', ' ', ucfirst($component['component_type'])); ?>
                                        </span>
                                        <span style="color: #64748b; font-size: 0.875rem;">
                                            <?php echo date('H:i', strtotime($component['created_at'])); ?>
                                        </span>
                                    </div>
                                    <div style="color: #64748b; font-size: 0.875rem;">
                                        <?php
                                        $data = json_decode($component['component_data'], true);
                                        echo $data['summary'] ?? 'Activity recorded';
                                        ?>
                                        <?php if ($component['staff_name']): ?>
                                            <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">
                                                By: <?php echo $component['staff_name']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                        <h4 style="font-weight: 600; margin-bottom: 0.5rem;">Quick Actions</h4>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <button class="btn" style="background: #f8fafc; border: 1px solid #e2e8f0;" onclick="window.print()">
                                <i class="fas fa-print"></i> Print Summary
                            </button>
                            <a href="ehr_dashboard.php" class="btn" style="background: #f8fafc; border: 1px solid #e2e8f0; text-align: center; text-decoration: none;">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Set current date for follow-up date field
        const followUpDate = document.querySelector('input[name="follow_up_date"]');
        if (followUpDate && !followUpDate.value) {
            const nextWeek = new Date();
            nextWeek.setDate(nextWeek.getDate() + 7);
            followUpDate.value = nextWeek.toISOString().split('T')[0];
        }
        
        // Handle "Save & Request Labs" button
        const requestLabsBtn = document.querySelector('button[name="request_labs"]');
        if (requestLabsBtn) {
            requestLabsBtn.addEventListener('click', function(e) {
                // Ensure assessment is filled before requesting labs
                const assessmentField = document.querySelector('textarea[name="assessment"]');
                if (!assessmentField.value.trim()) {
                    e.preventDefault();
                    alert('Please fill in the Assessment field before requesting lab tests.');
                    assessmentField.focus();
                }
            });
        }
    });
    </script>
</body>
</html>