<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

// Get messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);

// Handle new prescription
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_prescription'])) {
    $patient_id = $_POST['patient_id'];
    $medication_name = $_POST['medication_name'];
    $dosage = $_POST['dosage'];
    $frequency = $_POST['frequency'];
    $duration = $_POST['duration'];
    $instructions = $_POST['instructions'];
    $doctor_id = $_SESSION['user_id'] ?? $_SESSION['admin'] ?? 1; // Fallback to admin if user_id not set
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO prescriptions (patient_id, doctor_id, medication_name, dosage, frequency, duration, instructions, status, dispensed, prescription_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 0, NOW())
        ");
        $stmt->execute([$patient_id, $doctor_id, $medication_name, $dosage, $frequency, $duration, $instructions]);
        
        $_SESSION['success'] = "Prescription created successfully and sent to pharmacy!";
        // header("Location: prescriptions.php");
        // exit();
    } catch (PDOException $e) {
        $error_message = "Error creating prescription: " . $e->getMessage();
    }
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $prescription_id = $_POST['prescription_id'];
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("UPDATE prescriptions SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $prescription_id]);
        
        $_SESSION['success'] = "Prescription status updated successfully!";
        // header("Location: prescriptions.php");
        // exit();
    } catch (PDOException $e) {
        $error_message = "Error updating prescription: " . $e->getMessage();
    }
}

// Fetch prescriptions with filters
$status_filter = $_GET['status'] ?? '';
$search_term = $_GET['search'] ?? '';

$query = "
    SELECT p.*, 
           pt.first_name, pt.last_name, pt.patient_id,
           u.first_name as doctor_first_name, u.last_name as doctor_last_name,
           p.dispensed, p.dispensed_date
    FROM prescriptions p
    JOIN patients pt ON p.patient_id = pt.id
    JOIN users u ON p.doctor_id = u.id
    WHERE 1=1
";

$params = [];

if ($status_filter) {
    $query .= " AND p.status = ?";
    $params[] = $status_filter;
}

if ($search_term) {
    $query .= " AND (pt.first_name LIKE ? OR pt.last_name LIKE ? OR pt.patient_id LIKE ? OR p.medication_name LIKE ?)";
    $search_like = "%$search_term%";
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
}

$query .= " ORDER BY p.prescription_date DESC, p.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$prescriptions = $stmt->fetchAll();

// Get status counts for filters
$status_counts_stmt = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM prescriptions 
    GROUP BY status
");
$status_counts = $status_counts_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get dispensing statistics
$dispensing_stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN dispensed = 0 THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN dispensed = 1 THEN 1 ELSE 0 END) as dispensed
    FROM prescriptions 
    WHERE status = 'active'
")->fetch();

// Fetch patients for dropdown
$patients_stmt = $pdo->query("SELECT id, first_name, last_name, patient_id FROM patients ORDER BY first_name, last_name");
$patients = $patients_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions - AuraCare HMS</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            margin-top: 0.5rem;
        }
        
        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            text-align: center;
            border-left: 4px solid #3b82f6;
        }
        
        .stat-card.warning { border-left-color: #f59e0b; }
        .stat-card.success { border-left-color: #10b981; }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 0.5rem 0;
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .search-box {
            flex: 1;
            min-width: 300px;
            position: relative;
        }
        
        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .status-filters {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .status-filter {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 2rem;
            font-size: 0.875rem;
            text-decoration: none;
            color: #6b7280;
            transition: all 0.2s;
        }
        
        .status-filter.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        
        .btn {
            padding: 0.75rem 1.5rem;
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
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
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
        
        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-active { background: #dcfce7; color: #166534; }
        .status-completed { background: #dbeafe; color: #1e40af; }
        .status-cancelled { background: #f3f4f6; color: #6b7280; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-dispensed { background: #dcfce7; color: #166534; }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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
        
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
            justify-content: space-between;
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
        
        .info-banner {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .info-banner p {
            margin: 0;
            color: #1e40af;
            font-size: 0.875rem;
        }
        
        .info-banner strong {
            font-weight: 600;
        }
        
        .dispensing-status {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
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
                    <i class="fas fa-prescription"></i>
                    Prescriptions Management
                </h1>
                <p class="page-subtitle">Create and manage patient prescriptions</p>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <a href="pharmacy.php?tab=prescriptions" class="btn btn-secondary">
                    <i class="fas fa-clinic-medical"></i> View in Pharmacy
                </a>
                <button class="btn btn-primary" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i> New Prescription
                </button>
            </div>
        </div>
        
        <!-- Info Banner -->
        <div class="info-banner">
            <p>
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> All active prescriptions are automatically sent to the pharmacy for dispensing. 
                Pharmacists can view and dispense medications from the Pharmacy module.
            </p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <p class="stat-number"><?php echo $dispensing_stats['total'] ?? 0; ?></p>
                <p class="stat-label">Total Active</p>
            </div>
            <div class="stat-card warning">
                <p class="stat-number"><?php echo $dispensing_stats['pending'] ?? 0; ?></p>
                <p class="stat-label">Pending Dispensing</p>
            </div>
            <div class="stat-card success">
                <p class="stat-number"><?php echo $dispensing_stats['dispensed'] ?? 0; ?></p>
                <p class="stat-label">Dispensed</p>
            </div>
        </div>
        
        <div class="filters">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <form method="GET" style="margin: 0;">
                    <input type="text" name="search" placeholder="Search by patient name, ID, or medication..." 
                           value="<?php echo htmlspecialchars($search_term); ?>"
                           onchange="this.form.submit()">
                </form>
            </div>
            <div class="status-filters">
                <a href="prescriptions.php" class="status-filter <?php echo !$status_filter ? 'active' : ''; ?>">
                    All (<?php echo array_sum($status_counts); ?>)
                </a>
                <?php foreach ($status_counts as $status => $count): ?>
                <a href="prescriptions.php?status=<?php echo $status; ?>" 
                   class="status-filter <?php echo $status_filter == $status ? 'active' : ''; ?>">
                    <?php echo ucfirst($status); ?> (<?php echo $count; ?>)
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-list"></i>
                    All Prescriptions
                </h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Prescription ID</th>
                            <th>Patient</th>
                            <th>Medication</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Duration</th>
                            <th>Prescribing Doctor</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($prescriptions) > 0): ?>
                            <?php foreach ($prescriptions as $prescription): ?>
                            <tr>
                                <td><strong>#RX-<?php echo str_pad($prescription['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']); ?></strong><br>
                                    <small style="color: #64748b;">ID: <?php echo htmlspecialchars($prescription['patient_id']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($prescription['medication_name']); ?></td>
                                <td><?php echo htmlspecialchars($prescription['dosage']); ?></td>
                                <td><?php echo htmlspecialchars($prescription['frequency']); ?></td>
                                <td><?php echo htmlspecialchars($prescription['duration']); ?></td>
                                <td>Dr. <?php echo htmlspecialchars($prescription['doctor_first_name'] . ' ' . $prescription['doctor_last_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($prescription['prescription_date'])); ?></td>
                                <td>
                                    <span class="status status-<?php echo $prescription['status']; ?>">
                                        <?php echo ucfirst($prescription['status']); ?>
                                    </span>
                                    <?php if ($prescription['status'] == 'active'): ?>
                                    <div class="dispensing-status">
                                        <?php if ($prescription['dispensed']): ?>
                                        <span class="status status-dispensed">
                                            <i class="fas fa-check"></i> Dispensed
                                        </span>
                                        <br><small style="color: #64748b;"><?php echo date('M d, Y', strtotime($prescription['dispensed_date'])); ?></small>
                                        <?php else: ?>
                                        <span class="status status-pending">
                                            <i class="fas fa-clock"></i> Awaiting Pharmacy
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-primary btn-sm" 
                                            onclick="openUpdateModal(<?php echo $prescription['id']; ?>, '<?php echo $prescription['status']; ?>')">
                                        <i class="fas fa-edit"></i> Update
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="empty-state">
                                    <i class="fas fa-prescription"></i>
                                    <h3>No prescriptions found</h3>
                                    <p>No prescriptions match your search criteria.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Create Prescription Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Create New Prescription</h3>
                <button class="modal-close" onclick="closeCreateModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="createForm">
                    <input type="hidden" name="create_prescription" value="1">
                    
                    <div class="form-group">
                        <label for="patient_id">Patient: *</label>
                        <select name="patient_id" id="patient_id" class="form-control" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="medication_name">Medication Name: *</label>
                            <input type="text" name="medication_name" id="medication_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="dosage">Dosage: *</label>
                            <input type="text" name="dosage" id="dosage" class="form-control" required 
                                   placeholder="e.g., 500mg">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="frequency">Frequency: *</label>
                            <input type="text" name="frequency" id="frequency" class="form-control" required
                                   placeholder="e.g., Twice daily">
                        </div>
                        
                        <div class="form-group">
                            <label for="duration">Duration: *</label>
                            <input type="text" name="duration" id="duration" class="form-control" required
                                   placeholder="e.g., 7 days">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="instructions">Instructions:</label>
                        <textarea name="instructions" id="instructions" class="form-control" rows="4"
                                  placeholder="Additional instructions for the patient..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-save"></i> Create Prescription
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Update Status Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Prescription Status</h3>
                <button class="modal-close" onclick="closeUpdateModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="updateForm">
                    <input type="hidden" name="prescription_id" id="update_prescription_id">
                    <input type="hidden" name="update_status" value="1">
                    
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status" class="form-control" required>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function openCreateModal() {
            document.getElementById('createModal').style.display = 'block';
        }
        
        function closeCreateModal() {
            document.getElementById('createModal').style.display = 'none';
        }
        
        function openUpdateModal(prescriptionId, currentStatus) {
            document.getElementById('update_prescription_id').value = prescriptionId;
            document.getElementById('status').value = currentStatus;
            document.getElementById('updateModal').style.display = 'block';
        }
        
        function closeUpdateModal() {
            document.getElementById('updateModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const createModal = document.getElementById('createModal');
            const updateModal = document.getElementById('updateModal');
            
            if (event.target == createModal) {
                closeCreateModal();
            }
            if (event.target == updateModal) {
                closeUpdateModal();
            }
        }
        
        // Auto-submit search on enter
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        this.form.submit();
                    }
                });
            }
        });
    </script>
</body>
</html>
<?php include "includes/footer.php"; ?>