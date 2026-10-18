<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

// Check authorization
// if ($_SESSION['role'] != 'super_admin' && $_SESSION['role'] != 'billing_manager' && $_SESSION['role'] != 'doctor') {
//     header("Location: login.php");
//     exit();
// }

// Get messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);

// Handle patient discharge
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['discharge_patient'])) {
    $admission_id = $_POST['admission_id'];
    $discharge_summary = $_POST['discharge_summary'];
    $discharge_instructions = $_POST['discharge_instructions'];
    $follow_up_date = $_POST['follow_up_date'];
    
    try {
        // Update admission record
        $stmt = $pdo->prepare("
            UPDATE admissions 
            SET status = 'discharged', 
                discharge_date = NOW(),
                discharge_summary = ?,
                discharge_instructions = ?,
                follow_up_date = ?
            WHERE id = ?
        ");
        $stmt->execute([$discharge_summary, $discharge_instructions, $follow_up_date, $admission_id]);
        
        $_SESSION['success'] = "Patient discharged successfully! Final bill has been generated.";
        header("Location: patient_discharge.php");
        exit();
        
    } catch (PDOException $e) {
        $error_message = "Error discharging patient: " . $e->getMessage();
    }
}

// Handle bill generation for discharge
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_final_bill'])) {
    $admission_id = $_POST['admission_id'];
    
    try {
        // Get admission details
        $stmt = $pdo->prepare("
            SELECT a.*, p.id as patient_id, p.first_name, p.last_name, p.patient_id as patient_code
            FROM admissions a
            JOIN patients p ON a.patient_id = p.id
            WHERE a.id = ?
        ");
        $stmt->execute([$admission_id]);
        $admission = $stmt->fetch();
        
        if ($admission) {
            // Calculate length of stay
            $admission_date = new DateTime($admission['admission_date']);
            $discharge_date = new DateTime();
            $days_stayed = $admission_date->diff($discharge_date)->days + 1;
            
            // Generate bill number
            $bill_number = 'BIL-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Calculate room charges
            $room_charge_per_day = 5000; // Default room charge
            $total_room_charges = $days_stayed * $room_charge_per_day;
            
            // Create final bill
            $stmt = $pdo->prepare("
                INSERT INTO patient_bills 
                (bill_number, patient_id, admission_id, bill_type, total_amount, due_amount, bill_date, status, created_by)
                VALUES (?, ?, ?, 'inpatient', ?, ?, CURDATE(), 'generated', ?)
            ");
            $stmt->execute([
                $bill_number, 
                $admission['patient_id'], 
                $admission_id, 
                $total_room_charges, 
                $total_room_charges,
                $_SESSION['user_id']
            ]);
            
            $bill_id = $pdo->lastInsertId();
            
            // Add room charges to bill items
            $stmt = $pdo->prepare("
                INSERT INTO bill_items 
                (bill_id, item_description, quantity, unit_price, total_price, item_date)
                VALUES (?, 'Room Charges - ' || ? || ' days', ?, ?, ?, CURDATE())
            ");
            $stmt->execute([
                $bill_id, 
                $admission['ward'] ?: 'General Ward',
                $days_stayed,
                $room_charge_per_day,
                $total_room_charges
            ]);
            
            $_SESSION['success'] = "Final bill generated successfully!";
            header("Location: patient_discharge.php");
            exit();
        }
        
    } catch (PDOException $e) {
        $error_message = "Error generating final bill: " . $e->getMessage();
    }
}

// Fetch current inpatients
$inpatients = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, p.first_name, p.last_name, p.patient_id, p.date_of_birth, p.gender,
               u.first_name as doctor_first_name, u.last_name as doctor_last_name,
               DATEDIFF(CURDATE(), DATE(a.admission_date)) as days_stayed
        FROM admissions a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON a.doctor_id = u.id
        WHERE a.status = 'admitted'
        ORDER BY a.admission_date DESC
    ");
    $stmt->execute();
    $inpatients = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching inpatients: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Discharge - AuraCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0;
            color: #334155;
            font-size: 1.875rem;
            font-weight: 600;
        }
        
        .page-subtitle {
            color: #64748b;
            font-size: 0.875rem;
            margin: 0.5rem 0 0 0;
        }
        
        .card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
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
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        th {
            background: #f9fafb;
            font-weight: 600;
            font-size: 0.875rem;
            color: #374151;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
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
        
        .modal {
            display: none;
            position: fixed;
            z-index: 50;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 0.75rem;
            width: 90%;
            max-width: 600px;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .patient-info {
            background: #f8fafc;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            font-size: 0.875rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
        }
        
        .info-label {
            font-weight: 500;
            color: #374151;
        }
        
        .info-value {
            color: #6b7280;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }
    </style>
</head>
<body>
    <div class="main-content">
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
        
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-sign-out-alt"></i>
                    Patient Discharge
                </h1>
                <p class="page-subtitle">Manage inpatient discharge and generate final bills</p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-procedures"></i>
                    Current Inpatients
                </h2>
            </div>
            <div class="card-body">
                <?php if (count($inpatients) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Admission Date</th>
                                <th>Days Stayed</th>
                                <th>Ward/Room</th>
                                <th>Attending Doctor</th>
                                <th>Diagnosis</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inpatients as $inpatient): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($inpatient['first_name'] . ' ' . $inpatient['last_name']); ?></strong><br>
                                    <small>ID: <?php echo htmlspecialchars($inpatient['patient_id']); ?></small><br>
                                    <small>DOB: <?php echo date('M j, Y', strtotime($inpatient['date_of_birth'])); ?> • <?php echo htmlspecialchars($inpatient['gender']); ?></small>
                                </td>
                                <td><?php echo date('M j, Y g:i A', strtotime($inpatient['admission_date'])); ?></td>
                                <td><?php echo $inpatient['days_stayed']; ?> days</td>
                                <td>
                                    <?php if ($inpatient['ward']): ?>
                                        <?php echo htmlspecialchars($inpatient['ward']); ?>
                                        <?php if ($inpatient['room_number']): ?>
                                            <br>Room: <?php echo htmlspecialchars($inpatient['room_number']); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>Dr. <?php echo htmlspecialchars($inpatient['doctor_first_name'] . ' ' . $inpatient['doctor_last_name']); ?></td>
                                <td><?php echo htmlspecialchars($inpatient['diagnosis'] ?: 'Not recorded'); ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button class="btn btn-sm btn-primary" 
                                                onclick="openDischargeModal(<?php echo $inpatient['id']; ?>)">
                                            <i class="fas fa-sign-out-alt"></i> Discharge
                                        </button>
                                        <button class="btn btn-sm btn-success" 
                                                onclick="openBillModal(<?php echo $inpatient['id']; ?>)">
                                            <i class="fas fa-file-invoice"></i> Generate Bill
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-procedures"></i>
                    <h3>No Current Inpatients</h3>
                    <p>There are no patients currently admitted in the hospital.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Discharge Modal -->
    <div id="dischargeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Discharge Patient</h3>
                <button class="modal-close" onclick="closeDischargeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="dischargeForm">
                    <input type="hidden" name="admission_id" id="discharge_admission_id">
                    <input type="hidden" name="discharge_patient" value="1">
                    
                    <div id="dischargePatientInfo" class="patient-info">
                        <!-- Patient info will be loaded here -->
                    </div>
                    
                    <div class="form-group">
                        <label for="discharge_summary">Discharge Summary:</label>
                        <textarea name="discharge_summary" id="discharge_summary" class="form-control form-textarea" 
                                  placeholder="Enter discharge summary, final diagnosis, and treatment outcome..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="discharge_instructions">Discharge Instructions:</label>
                        <textarea name="discharge_instructions" id="discharge_instructions" class="form-control form-textarea" 
                                  placeholder="Enter post-discharge care instructions, medication guidelines, and restrictions..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="follow_up_date">Follow-up Date:</label>
                        <input type="date" name="follow_up_date" id="follow_up_date" class="form-control" 
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-sign-out-alt"></i> Complete Discharge
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Generate Bill Modal -->
    <div id="billModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Generate Final Bill</h3>
                <button class="modal-close" onclick="closeBillModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="billForm">
                    <input type="hidden" name="admission_id" id="bill_admission_id">
                    <input type="hidden" name="generate_final_bill" value="1">
                    
                    <div id="billPatientInfo" class="patient-info">
                        <!-- Patient info will be loaded here -->
                    </div>
                    
                    <div class="alert alert-success">
                        <i class="fas fa-info-circle"></i>
                        This will generate the final bill including room charges for the entire stay.
                        Additional services and medications can be added to the bill later.
                    </div>
                    
                    <button type="submit" class="btn btn-success" style="width: 100%;">
                        <i class="fas fa-file-invoice"></i> Generate Final Bill
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function openDischargeModal(admissionId) {
            document.getElementById('discharge_admission_id').value = admissionId;
            // Load patient info (you would typically fetch this via AJAX)
            document.getElementById('dischargeModal').style.display = 'block';
        }
        
        function closeDischargeModal() {
            document.getElementById('dischargeModal').style.display = 'none';
        }
        
        function openBillModal(admissionId) {
            document.getElementById('bill_admission_id').value = admissionId;
            // Load patient info (you would typically fetch this via AJAX)
            document.getElementById('billModal').style.display = 'block';
        }
        
        function closeBillModal() {
            document.getElementById('billModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const dischargeModal = document.getElementById('dischargeModal');
            const billModal = document.getElementById('billModal');
            
            if (event.target == dischargeModal) {
                closeDischargeModal();
            }
            if (event.target == billModal) {
                closeBillModal();
            }
        }
        
        // You would typically add AJAX calls here to load patient details
        // For now, we'll use the basic functionality
    </script>
</body>
</html>
<?php include "includes/footer.php"; ?>