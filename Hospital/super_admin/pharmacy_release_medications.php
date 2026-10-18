<?php
session_start();

if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

$message = "";
$username = $_SESSION['username'] ?? 'Admin';

// Get patients who have paid and are ready to collect medications
$patients_ready_sql = "
    SELECT DISTINCT
        a.appoid,
        a.pid,
        p.pname,
        p.ptel,
        bp.receipt_no,
        bp.payment_date,
        b.id as billing_id,
        b.bill_no,
        bp.amount as pharmacy_amount,
        bp.receipt_released,
        bp.released_at,
        bp.released_by
    FROM appointment a
    INNER JOIN patient p ON a.pid = p.pid
    INNER JOIN billing b ON a.appoid = b.appoid
    INNER JOIN billing_payments bp ON b.id = bp.reference_id AND bp.type = 'pharmacy'
    WHERE a.workflow_stage = 'payment_complete'
    AND bp.status = 'paid'
    AND bp.receipt_released = 0
    ORDER BY bp.payment_date ASC
";

$patients_ready = $database->query($patients_ready_sql);

// Handle medication release
if ($_POST && isset($_POST['release_medication'])) {
    $appoid = $_POST['appoid'];
    $billing_id = $_POST['billing_id'];
    $receipt_no = $_POST['receipt_no'];
    
    try {
        $database->begin_transaction();
        
        // Update billing_payments to mark receipt as released
        $update_payment = "UPDATE billing_payments 
                          SET receipt_released = 1, 
                              released_by = ?, 
                              released_at = NOW() 
                          WHERE receipt_no = ? AND type = 'pharmacy'";
        $stmt1 = $database->prepare($update_payment);
        $stmt1->bind_param("ss", $username, $receipt_no);
        $stmt1->execute();
        
        // Update billing table
        $update_billing = "UPDATE billing 
                          SET billing_stage = 'released' 
                          WHERE id = ?";
        $stmt2 = $database->prepare($update_billing);
        $stmt2->bind_param("i", $billing_id);
        $stmt2->execute();
        
        // Update e-prescriptions
        $update_prescriptions = "UPDATE ehr_prescriptions 
                                SET status = 'released' 
                                WHERE appoid = ?";
        $stmt3 = $database->prepare($update_prescriptions);
        $stmt3->bind_param("i", $appoid);
        $stmt3->execute();
        
        // Update appointment to completed
        $update_appointment = "UPDATE appointment 
                              SET workflow_stage = 'completed' 
                              WHERE appoid = ?";
        $stmt4 = $database->prepare($update_appointment);
        $stmt4->bind_param("i", $appoid);
        $stmt4->execute();
        
        $database->commit();
        
        $message = "<div class='alert alert-success'>
            <i class='fas fa-check-circle'></i> Medications released successfully!<br>
            <strong>Receipt No: {$receipt_no}</strong><br>
            Patient workflow completed.
        </div>";
        
        // Refresh the page
        header("Refresh:2");
        
    } catch (Exception $e) {
        $database->rollback();
        $message = "<div class='alert alert-error'>Error releasing medications: " . $e->getMessage() . "</div>";
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Release Medications - MediCare</title>
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5568d3;
            --secondary: #2ecc71;
            --danger: #e74c3c;
            --warning: #f39c12;
            --info: #3498db;
            --success: #27ae60;
            --light: #f8f9fa;
            --dark: #2c3e50;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: #f5f7fa;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #219955;
        }

        .alert {
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 5px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 5px solid #dc3545;
        }

        .workflow-info {
            background: linear-gradient(135deg, var(--success) 0%, #219955 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
        }

        .workflow-info h3 {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .workflow-steps {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .workflow-steps ol {
            margin: 10px 0 0 20px;
        }

        .workflow-steps li {
            margin: 8px 0;
            padding: 5px 0;
        }

        .patients-table {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .patients-table h2 {
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--dark);
            font-size: 13px;
            text-transform: uppercase;
            border-bottom: 2px solid #e0e0e0;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f0f0f0;
            color: #555;
        }

        tbody tr {
            transition: all 0.3s ease;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .release-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
        }

        .release-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 700px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-header h3 {
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-modal {
            font-size: 28px;
            cursor: pointer;
            color: #999;
        }

        .close-modal:hover {
            color: var(--danger);
        }

        .medication-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: var(--dark);
        }

        .warning-box {
            background: #fff3cd;
            border-left: 4px solid var(--warning);
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .warning-box strong {
            color: var(--dark);
            display: block;
            margin-bottom: 10px;
        }

        .checklist {
            margin: 15px 0;
        }

        .checklist label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: white;
            border-radius: 5px;
            margin: 8px 0;
            cursor: pointer;
        }

        .checklist input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-hand-holding-medical"></i>
                Release Medications
            </h1>
            <a href="pharmacy.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Pharmacy
            </a>
        </div>

        <?php if ($message): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <!-- Workflow Information -->
        <div class="workflow-info">
            <h3><i class="fas fa-clipboard-check"></i> MEDICATION RELEASE WORKFLOW</h3>
            <div class="workflow-steps">
                <p><strong>Final Stage: Release Medications to Patient</strong></p>
                <ol>
                    <li>✓ Patient consulted with doctor</li>
                    <li>✓ E-prescription created</li>
                    <li>✓ Pharmacy dispensed medications & generated bill</li>
                    <li>✓ Finance processed payment & printed receipt</li>
                    <li><strong>→ YOU ARE HERE: Verify receipt and release medications</strong></li>
                    <li>→ Patient receives medications - Workflow Complete!</li>
                </ol>
                <p style="margin-top: 15px;"><strong>Important:</strong> Always verify the receipt before releasing medications.</p>
            </div>
        </div>

        <!-- Patients Ready to Collect -->
        <div class="patients-table">
            <h2><i class="fas fa-clipboard-list"></i> Patients Ready to Collect Medications</h2>
            
            <table>
                <thead>
                    <tr>
                        <th>Receipt No.</th>
                        <th>Patient Name</th>
                        <th>Contact</th>
                        <th>Payment Date</th>
                        <th>Amount Paid</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($patients_ready && $patients_ready->num_rows > 0): ?>
                        <?php while ($patient = $patients_ready->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($patient['receipt_no']); ?></strong></td>
                            <td><?php echo htmlspecialchars($patient['pname']); ?></td>
                            <td><?php echo htmlspecialchars($patient['ptel']); ?></td>
                            <td><?php echo date('M d, Y g:i A', strtotime($patient['payment_date'])); ?></td>
                            <td style="font-weight: bold; color: var(--success);">
                                KES <?php echo number_format($patient['pharmacy_amount'], 2); ?>
                            </td>
                            <td>
                                <span class="badge badge-success">
                                    <i class="fas fa-check-circle"></i> Paid
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-success" onclick="openReleaseModal(<?php echo htmlspecialchars(json_encode($patient)); ?>)">
                                    <i class="fas fa-hand-holding-medical"></i> Release Medications
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <i class="fas fa-check-circle"></i>
                                    <h3>No Medications Pending Release</h3>
                                    <p>All patients have collected their medications</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Release Modal -->
    <div class="release-modal" id="releaseModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-hand-holding-medical"></i> Release Medications</h3>
                <span class="close-modal" onclick="closeReleaseModal()">&times;</span>
            </div>
            
            <form method="POST" id="releaseForm">
                <input type="hidden" name="release_medication" value="1">
                <input type="hidden" name="appoid" id="modal_appoid">
                <input type="hidden" name="billing_id" id="modal_billing_id">
                <input type="hidden" name="receipt_no" id="modal_receipt_no">
                
                <div class="medication-details">
                    <h4 style="margin-bottom: 15px; color: var(--dark);">
                        <i class="fas fa-info-circle"></i> Patient & Payment Details
                    </h4>
                    <div class="detail-row">
                        <span class="detail-label">Receipt Number:</span>
                        <span id="display_receipt_no"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Patient Name:</span>
                        <span id="display_patient_name"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Contact:</span>
                        <span id="display_patient_contact"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Payment Date:</span>
                        <span id="display_payment_date"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Amount Paid:</span>
                        <span id="display_amount" style="font-weight: bold; color: var(--success);"></span>
                    </div>
                </div>
                
                <div class="warning-box">
                    <strong><i class="fas fa-exclamation-triangle"></i> Verification Checklist</strong>
                    <div class="checklist">
                        <label>
                            <input type="checkbox" required>
                            Receipt number matches the patient record
                        </label>
                        <label>
                            <input type="checkbox" required>
                            Patient identity verified (ID, name confirmation)
                        </label>
                        <label>
                            <input type="checkbox" required>
                            All medications are correctly labeled
                        </label>
                        <label>
                            <input type="checkbox" required>
                            Patient counseled on medication usage
                        </label>
                        <label>
                            <input type="checkbox" required>
                            Patient signed collection acknowledgment
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-success" style="width: 100%; padding: 15px; font-size: 16px; margin-top: 20px;">
                    <i class="fas fa-check-circle"></i> Confirm & Release Medications
                </button>
            </form>
        </div>
    </div>

    <script>
        function openReleaseModal(patient) {
            document.getElementById('modal_appoid').value = patient.appoid;
            document.getElementById('modal_billing_id').value = patient.billing_id;
            document.getElementById('modal_receipt_no').value = patient.receipt_no;
            
            document.getElementById('display_receipt_no').textContent = patient.receipt_no;
            document.getElementById('display_patient_name').textContent = patient.pname;
            document.getElementById('display_patient_contact').textContent = patient.ptel;
            document.getElementById('display_payment_date').textContent = new Date(patient.payment_date).toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            document.getElementById('display_amount').textContent = 'KES ' + parseFloat(patient.pharmacy_amount).toLocaleString('en-KE', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            
            document.getElementById('releaseModal').classList.add('active');
        }

        function closeReleaseModal() {
            document.getElementById('releaseModal').classList.remove('active');
            document.getElementById('releaseForm').reset();
        }

        // Close modal when clicking outside
        document.getElementById('releaseModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeReleaseModal();
            }
        });

        // Form validation
        document.getElementById('releaseForm').addEventListener('submit', function(e) {
            const checkboxes = this.querySelectorAll('input[type="checkbox"]');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            if (!allChecked) {
                e.preventDefault();
                alert('Please complete all verification checklist items before releasing medications.');
            }
        });
    </script>
</body>
</html>