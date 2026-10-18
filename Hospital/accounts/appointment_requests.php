<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

// Get current tab
$tab = $_GET['tab'] ?? 'pending';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['request_id'])) {
        $request_id = $_POST['request_id'];
        $action = $_POST['action'];
        $reason = $_POST['reason'] ?? '';
        
        if ($action === 'approve') {
            // Approve the request and create an appointment
            $stmt = $pdo->prepare("
                UPDATE appointment_requests 
                SET status = 'approved', 
                    processed_by = ?, 
                    processed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $request_id]);
            
            // Create actual appointment from the request
            $request_stmt = $pdo->prepare("
                SELECT * FROM appointment_requests WHERE id = ?
            ");
            $request_stmt->execute([$request_id]);
            $request = $request_stmt->fetch();
            
            if ($request) {
                $appointment_stmt = $pdo->prepare("
                    INSERT INTO appointments 
                    (patient_id, doctor_id, appointment_date, appointment_time, type, notes, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, 'scheduled', ?)
                ");
                $appointment_stmt->execute([
                    $request['patient_id'],
                    $request['doctor_id'],
                    $request['preferred_date'],
                    $request['preferred_time'],
                    $request['type'],
                    $request['notes'],
                    $_SESSION['user_id']
                ]);
            }
            
        } elseif ($action === 'reject') {
            // Reject the request
            $stmt = $pdo->prepare("
                UPDATE appointment_requests 
                SET status = 'rejected', 
                    rejection_reason = ?,
                    processed_by = ?, 
                    processed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$reason, $_SESSION['user_id'], $request_id]);
        }
        
        header("Location: appointment_requests.php?tab=$tab");
        exit;
    }
}

// Get appointment requests based on tab
function getAppointmentRequests($pdo, $status) {
    $sql = "
        SELECT ar.*, 
               CONCAT(p.first_name, ' ', p.last_name) as patient_name,
               CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
               u.first_name as processed_by_name
        FROM appointment_requests ar
        LEFT JOIN patients p ON ar.patient_id = p.id
        LEFT JOIN users d ON ar.doctor_id = d.id
        LEFT JOIN users u ON ar.processed_by = u.id
        WHERE ar.status = ?
        ORDER BY ar.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status]);
    return $stmt->fetchAll();
}

$pending_requests = getAppointmentRequests($pdo, 'pending');
$approved_requests = getAppointmentRequests($pdo, 'approved');
$rejected_requests = getAppointmentRequests($pdo, 'rejected');

// Get all doctors for dropdown
$doctors_stmt = $pdo->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as name 
    FROM users 
    WHERE role = 'Doctor' AND status = 'active'
    ORDER BY first_name
");
$doctors = $doctors_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Requests - MediCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.875rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #64748b;
            font-size: 0.875rem;
        }

        /* Tabs Navigation */
        .requests-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            background: white;
            padding: 0.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .tab-btn {
            flex: 1;
            padding: 0.75rem 1rem;
            border: none;
            background: transparent;
            color: #64748b;
            border-radius: 0.5rem;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .tab-btn:hover {
            background: #f8fafc;
            color: #374151;
        }

        .tab-btn.active {
            background: #3b82f6;
            color: white;
        }

        .tab-badge {
            background: #ef4444;
            color: white;
            border-radius: 9999px;
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .tab-btn.active .tab-badge {
            background: white;
            color: #3b82f6;
        }

        /* Content Cards */
        .content-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.5rem;
        }

        .card-header p {
            color: #64748b;
            font-size: 0.875rem;
        }

        .card-content {
            padding: 1.5rem;
        }

        /* Request Cards */
        .request-cards {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .request-card {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1.5rem;
            transition: all 0.2s;
        }

        .request-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .request-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .patient-info {
            flex: 1;
        }

        .patient-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .doctor-info {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .request-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
        }

        .detail-value {
            font-size: 0.875rem;
            color: #0f172a;
            font-weight: 500;
        }

        .urgency-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .urgency-high {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .urgency-medium {
            background: #fffbeb;
            color: #d97706;
            border: 1px solid #fed7aa;
        }

        .urgency-normal {
            background: #f0f9ff;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .request-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #374151;
        }

        .btn-outline:hover {
            background: #f9fafb;
        }

        /* Tables */
        .requests-table {
            width: 100%;
            border-collapse: collapse;
        }

        .requests-table th {
            background: #f8fafc;
            padding: 0.75rem 1rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
        }

        .requests-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.875rem;
        }

        .requests-table tr:last-child td {
            border-bottom: none;
        }

        .table-patient {
            font-weight: 600;
            color: #0f172a;
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0f172a;
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

        .form-control {
            width: 100%;
            padding: 0.625rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .modal-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e1;
        }

        .empty-state h3 {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #64748b;
        }

        .empty-state p {
            font-size: 0.875rem;
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }

            .request-details {
                grid-template-columns: 1fr;
            }

            .request-actions {
                flex-direction: column;
            }

            .table-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="page-header">
        <h1>Appointment Requests</h1>
        <p>Manage patient appointment requests.</p>
    </div>

    <div class="requests-tabs">
        <button class="tab-btn <?php echo $tab === 'pending' ? 'active' : ''; ?>" onclick="changeTab('pending')">
            Pending Requests
            <?php if (count($pending_requests) > 0): ?>
                <span class="tab-badge"><?php echo count($pending_requests); ?></span>
            <?php endif; ?>
        </button>
        <button class="tab-btn <?php echo $tab === 'approved' ? 'active' : ''; ?>" onclick="changeTab('approved')">
            Approved
        </button>
        <button class="tab-btn <?php echo $tab === 'rejected' ? 'active' : ''; ?>" onclick="changeTab('rejected')">
            Rejected
        </button>
    </div>

    <?php if ($tab === 'pending'): ?>
        <div class="content-card">
            <div class="card-header">
                <h2>Pending Appointment Requests</h2>
                <p>Review and process incoming appointment requests.</p>
            </div>
            <div class="card-content">
                <?php if (empty($pending_requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Pending Requests</h3>
                        <p>All appointment requests have been processed.</p>
                    </div>
                <?php else: ?>
                    <div class="request-cards">
                        <?php foreach ($pending_requests as $request): ?>
                            <div class="request-card">
                                <div class="request-header">
                                    <div class="patient-info">
                                        <div class="patient-name"><?php echo htmlspecialchars($request['patient_name']); ?></div>
                                        <div class="doctor-info">
                                            <?php 
                                            if ($request['doctor_id']) {
                                                echo "Dr. " . htmlspecialchars($request['doctor_name']);
                                            } else {
                                                echo "Any available doctor";
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="request-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Preferred Date</span>
                                        <span class="detail-value"><?php echo date('Y-m-d', strtotime($request['preferred_date'])); ?> <?php echo $request['preferred_time']; ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Type</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($request['type']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Urgency</span>
                                        <span class="urgency-badge urgency-<?php echo strtolower($request['urgency']); ?>">
                                            <?php echo htmlspecialchars($request['urgency']); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="request-actions">
                                    <button class="btn btn-outline" onclick="showRejectModal(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-times"></i>
                                        Reject
                                    </button>
                                    <button class="btn btn-primary" onclick="showApproveModal(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-check"></i>
                                        Approve
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($tab === 'approved'): ?>
        <div class="content-card">
            <div class="card-header">
                <h2>Approved Requests</h2>
                <p>View all approved appointment requests.</p>
            </div>
            <div class="card-content">
                <?php if (empty($approved_requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>No Approved Requests</h3>
                        <p>No appointment requests have been approved yet.</p>
                    </div>
                <?php else: ?>
                    <table class="requests-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Registered Doctor</th>
                                <th>Preferred Date</th>
                                <th>Type</th>
                                <th>Urgency</th>
                                <th>Scheduled</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($approved_requests as $request): ?>
                                <tr>
                                    <td class="table-patient"><?php echo htmlspecialchars($request['patient_name']); ?></td>
                                    <td>
                                        <?php 
                                        if ($request['doctor_id']) {
                                            echo "Dr. " . htmlspecialchars($request['doctor_name']);
                                        } else {
                                            echo "Any available doctor";
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($request['preferred_date'])); ?> <?php echo $request['preferred_time']; ?></td>
                                    <td><?php echo htmlspecialchars($request['type']); ?></td>
                                    <td>
                                        <span class="urgency-badge urgency-<?php echo strtolower($request['urgency']); ?>">
                                            <?php echo htmlspecialchars($request['urgency']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('Y-m-d g:i A', strtotime($request['processed_at'])); ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn btn-outline">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($tab === 'rejected'): ?>
        <div class="content-card">
            <div class="card-header">
                <h2>Rejected Requests</h2>
                <p>View all rejected appointment requests.</p>
            </div>
            <div class="card-content">
                <?php if (empty($rejected_requests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-times-circle"></i>
                        <h3>No Rejected Requests</h3>
                        <p>No appointment requests have been rejected yet.</p>
                    </div>
                <?php else: ?>
                    <div class="request-cards">
                        <?php foreach ($rejected_requests as $request): ?>
                            <div class="request-card">
                                <div class="request-header">
                                    <div class="patient-info">
                                        <div class="patient-name"><?php echo htmlspecialchars($request['patient_name']); ?></div>
                                        <div class="doctor-info">
                                            <?php 
                                            if ($request['doctor_id']) {
                                                echo "Dr. " . htmlspecialchars($request['doctor_name']);
                                            } else {
                                                echo "Any available doctor";
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="request-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Preferred Date</span>
                                        <span class="detail-value"><?php echo date('Y-m-d', strtotime($request['preferred_date'])); ?> <?php echo $request['preferred_time']; ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Type</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($request['type']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Urgency</span>
                                        <span class="urgency-badge urgency-<?php echo strtolower($request['urgency']); ?>">
                                            <?php echo htmlspecialchars($request['urgency']); ?>
                                        </span>
                                    </div>
                                    <div class="detail-item" style="grid-column: 1 / -1;">
                                        <span class="detail-label">Reason for Rejection</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($request['rejection_reason']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Approve Modal -->
<div id="approveModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Approve Appointment Request</h3>
        </div>
        <form id="approveForm" method="POST">
            <input type="hidden" name="request_id" id="approveRequestId">
            <input type="hidden" name="action" value="approve">
            
            <div class="form-group">
                <label class="form-label">Assign Doctor</label>
                <select class="form-control" name="doctor_id" required>
                    <option value="">Select a doctor</option>
                    <?php foreach ($doctors as $doctor): ?>
                        <option value="<?php echo $doctor['id']; ?>">Dr. <?php echo htmlspecialchars($doctor['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Appointment Date & Time</label>
                <input type="datetime-local" class="form-control" name="appointment_datetime" required>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('approveModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Approve Appointment</button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Reject Appointment Request</h3>
        </div>
        <form id="rejectForm" method="POST">
            <input type="hidden" name="request_id" id="rejectRequestId">
            <input type="hidden" name="action" value="reject">
            
            <div class="form-group">
                <label class="form-label">Reason for Rejection</label>
                <textarea class="form-control" name="reason" placeholder="Please provide a reason for rejecting this appointment request..." required></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('rejectModal')">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject Request</button>
            </div>
        </form>
    </div>
</div>

<script>
    function changeTab(tab) {
        window.location.href = `appointment_requests.php?tab=${tab}`;
    }

    function showApproveModal(requestId) {
        document.getElementById('approveRequestId').value = requestId;
        document.getElementById('approveModal').style.display = 'flex';
    }

    function showRejectModal(requestId) {
        document.getElementById('rejectRequestId').value = requestId;
        document.getElementById('rejectModal').style.display = 'flex';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
</script>

</body>
</html>