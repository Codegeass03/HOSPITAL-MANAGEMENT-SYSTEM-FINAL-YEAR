<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

// Check authorization
// if ($_SESSION['role'] != 'super_admin' && $_SESSION['role'] != 'doctor') {
//     header("Location: login.php");
//     exit();
// }

// Get messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);

// Handle medical history entry
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_history'])) {
    $patient_id = $_POST['patient_id'];
    $condition_name = $_POST['condition_name'];
    $diagnosis_date = $_POST['diagnosis_date'];
    $symptoms = $_POST['symptoms'];
    $treatment = $_POST['treatment'];
    $notes = $_POST['notes'];
    $status = $_POST['status'];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO medical_history (patient_id, condition_name, diagnosis_date, symptoms, treatment, notes, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$patient_id, $condition_name, $diagnosis_date, $symptoms, $treatment, $notes, $status]);
        
        $_SESSION['success'] = "Medical history added successfully!";
        header("Location: medical_history.php");
        exit();
    } catch (PDOException $e) {
        $error_message = "Error adding medical history: " . $e->getMessage();
    }
}

// Handle allergy entry
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_allergy'])) {
    $patient_id = $_POST['patient_id'];
    $allergy_name = $_POST['allergy_name'];
    $severity = $_POST['severity'];
    $reaction = $_POST['reaction'];
    $notes = $_POST['notes'];
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO allergies (patient_id, allergy_name, severity, reaction, notes) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$patient_id, $allergy_name, $severity, $reaction, $notes]);
        
        $_SESSION['success'] = "Allergy added successfully!";
        header("Location: medical_history.php");
        exit();
    } catch (PDOException $e) {
        $error_message = "Error adding allergy: " . $e->getMessage();
    }
}

// Fetch medical history with search
$search_term = $_GET['search'] ?? '';
$medical_history = [];
$allergies = [];

try {
    $query = "
        SELECT mh.*, 
               p.first_name, p.last_name, p.patient_id,
               p.date_of_birth, p.gender
        FROM medical_history mh
        JOIN patients p ON mh.patient_id = p.id
    ";

    $params = [];

    if ($search_term) {
        $query .= " WHERE (p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_id LIKE ? OR mh.condition_name LIKE ?)";
        $search_like = "%$search_term%";
        $params[] = $search_like;
        $params[] = $search_like;
        $params[] = $search_like;
        $params[] = $search_like;
    }

    $query .= " ORDER BY mh.diagnosis_date DESC, mh.id DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $medical_history = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching medical history: " . $e->getMessage();
}

try {
    // Fetch allergies
    $allergies_query = "
        SELECT a.*, 
               p.first_name, p.last_name, p.patient_id
        FROM allergies a
        JOIN patients p ON a.patient_id = p.id
        ORDER BY a.severity DESC, a.allergy_name
    ";
    $allergies_stmt = $pdo->query($allergies_query);
    $allergies = $allergies_stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching allergies: " . $e->getMessage();
}

// Fetch patients for dropdown
try {
    $patients_stmt = $pdo->query("SELECT id, first_name, last_name, patient_id FROM patients ORDER BY first_name, last_name");
    $patients = $patients_stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching patients: " . $e->getMessage();
    $patients = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical History - AuraCare HMS</title>
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
        
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .search-box {
            flex: 1;
            min-width: 300px;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
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
            justify-content: between;
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
        }
        
        .status-active { background: #dcfce7; color: #166534; }
        .status-resolved { background: #dbeafe; color: #1e40af; }
        .status-chronic { background: #fef3c7; color: #d97706; }
        
        .severity {
            padding: 0.25rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .severity-high { background: #fee2e2; color: #dc2626; }
        .severity-medium { background: #fef3c7; color: #d97706; }
        .severity-low { background: #dcfce7; color: #166534; }
        .severity-unknown { background: #f3f4f6; color: #6b7280; }
        
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
        
        .tabs {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 1.5rem;
        }
        
        .tab {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            font-size: 0.875rem;
            font-weight: 500;
            color: #6b7280;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }
        
        .tab.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
                    <i class="fas fa-file-medical"></i>
                    Medical History
                </h1>
                <p class="page-subtitle">Manage patient medical records and allergies</p>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add Record
            </button>
        </div>
        
        <div class="filters">
            <div class="search-box">
                <form method="GET">
                    <input type="text" name="search" placeholder="Search by patient name, ID, or condition..." 
                           value="<?php echo htmlspecialchars($search_term); ?>">
                </form>
            </div>
        </div>
        
        <div class="tabs">
            <button class="tab active" onclick="switchTab('medical')">Medical Conditions</button>
            <button class="tab" onclick="switchTab('allergies')">Allergies</button>
        </div>
        
        <!-- Medical Conditions Tab -->
        <div id="medical-tab" class="tab-content active">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-stethoscope"></i>
                        Medical Conditions
                    </h2>
                </div>
                <div class="table-responsive">
                    <?php if (count($medical_history) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Condition</th>
                                <th>Diagnosis Date</th>
                                <th>Symptoms</th>
                                <th>Treatment</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medical_history as $history): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($history['first_name'] . ' ' . $history['last_name']); ?></strong><br>
                                    <small>ID: <?php echo htmlspecialchars($history['patient_id']); ?></small><br>
                                    <small>DOB: <?php echo date('M j, Y', strtotime($history['date_of_birth'])); ?> • <?php echo htmlspecialchars($history['gender']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($history['condition_name']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($history['diagnosis_date'])); ?></td>
                                <td><?php echo htmlspecialchars($history['symptoms'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($history['treatment'] ?: 'N/A'); ?></td>
                                <td>
                                    <span class="status status-<?php echo $history['status']; ?>">
                                        <?php echo ucfirst($history['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-medical"></i>
                        <h3>No Medical History Found</h3>
                        <p>No medical conditions have been recorded yet.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Allergies Tab -->
        <div id="allergies-tab" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="fas fa-allergies"></i>
                        Allergies
                    </h2>
                </div>
                <div class="table-responsive">
                    <?php if (count($allergies) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Allergy</th>
                                <th>Severity</th>
                                <th>Reaction</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allergies as $allergy): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($allergy['first_name'] . ' ' . $allergy['last_name']); ?></strong><br>
                                    <small>ID: <?php echo htmlspecialchars($allergy['patient_id']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($allergy['allergy_name']); ?></td>
                                <td>
                                    <span class="severity severity-<?php echo strtolower($allergy['severity']); ?>">
                                        <?php echo ucfirst($allergy['severity']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($allergy['reaction'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($allergy['notes'] ?: 'N/A'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-allergies"></i>
                        <h3>No Allergies Recorded</h3>
                        <p>No allergies have been recorded for any patients.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Record Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Medical Record</h3>
                <button class="modal-close" onclick="closeAddModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="tabs">
                    <button class="tab active" onclick="switchFormTab('condition')">Medical Condition</button>
                    <button class="tab" onclick="switchFormTab('allergy')">Allergy</button>
                </div>
                
                <!-- Medical Condition Form -->
                <div id="condition-form" class="tab-content active">
                    <form method="POST" id="conditionForm">
                        <input type="hidden" name="add_history" value="1">
                        
                        <div class="form-group">
                            <label for="patient_id">Patient:</label>
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
                                <label for="condition_name">Condition Name:</label>
                                <input type="text" name="condition_name" id="condition_name" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="diagnosis_date">Diagnosis Date:</label>
                                <input type="date" name="diagnosis_date" id="diagnosis_date" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="symptoms">Symptoms:</label>
                            <textarea name="symptoms" id="symptoms" class="form-control" rows="3"
                                      placeholder="Describe the symptoms..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="treatment">Treatment:</label>
                            <textarea name="treatment" id="treatment" class="form-control" rows="3"
                                      placeholder="Describe the treatment plan..."></textarea>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="notes">Notes:</label>
                                <textarea name="notes" id="notes" class="form-control" rows="2"
                                          placeholder="Additional notes..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Status:</label>
                                <select name="status" id="status" class="form-control" required>
                                    <option value="active">Active</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="chronic">Chronic</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-save"></i> Add Medical Condition
                        </button>
                    </form>
                </div>
                
                <!-- Allergy Form -->
                <div id="allergy-form" class="tab-content">
                    <form method="POST" id="allergyForm">
                        <input type="hidden" name="add_allergy" value="1">
                        
                        <div class="form-group">
                            <label for="allergy_patient_id">Patient:</label>
                            <select name="patient_id" id="allergy_patient_id" class="form-control" required>
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
                                <label for="allergy_name">Allergy Name:</label>
                                <input type="text" name="allergy_name" id="allergy_name" class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="severity">Severity:</label>
                                <select name="severity" id="severity" class="form-control" required>
                                    <option value="high">High</option>
                                    <option value="medium">Medium</option>
                                    <option value="low">Low</option>
                                    <option value="unknown">Unknown</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="reaction">Reaction:</label>
                            <textarea name="reaction" id="reaction" class="form-control" rows="3"
                                      placeholder="Describe the allergic reaction..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="allergy_notes">Notes:</label>
                            <textarea name="notes" id="allergy_notes" class="form-control" rows="2"
                                      placeholder="Additional notes about the allergy..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-save"></i> Add Allergy
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        function switchTab(tab) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tab + '-tab').classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }
        
        function switchFormTab(formType) {
            // Hide all form contents
            document.querySelectorAll('#addModal .tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all form tabs
            document.querySelectorAll('#addModal .tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected form content
            document.getElementById(formType + '-form').classList.add('active');
            
            // Add active class to clicked form tab
            event.target.classList.add('active');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addModal');
            if (event.target == modal) {
                closeAddModal();
            }
        }
    </script>
</body>
</html>
<?php include "includes/footer.php"; ?>