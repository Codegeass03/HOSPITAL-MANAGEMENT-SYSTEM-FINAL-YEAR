<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

// Check authorization
// if ($_SESSION['role'] != 'super_admin' && $_SESSION['role'] != 'doctor' && $_SESSION['role'] != 'nurse') {
//     header("Location: login.php");
//     exit();
// }

// Get messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);

// Fetch vital signs with search - FROM CORRECT TABLE (patient_vitals)
$search_term = $_GET['search'] ?? '';
$patient_filter = $_GET['patient_id'] ?? '';
$vital_signs = [];

try {
    $query = "
        SELECT pv.*, 
               p.first_name, p.last_name, p.patient_id,
               p.date_of_birth, p.gender,
               u.first_name as recorded_by_first, u.last_name as recorded_by_last
        FROM patient_vitals pv
        JOIN patients p ON pv.patient_id = p.id
        LEFT JOIN users u ON pv.recorded_by = u.id
    ";

    $params = [];

    if ($search_term) {
        $query .= " WHERE (p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_id LIKE ?)";
        $search_like = "%$search_term%";
        $params[] = $search_like;
        $params[] = $search_like;
        $params[] = $search_like;
    }

    if ($patient_filter) {
        if (strpos($query, 'WHERE') !== false) {
            $query .= " AND p.id = ?";
        } else {
            $query .= " WHERE p.id = ?";
        }
        $params[] = $patient_filter;
    }

    $query .= " ORDER BY pv.recorded_at DESC, pv.id DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $vital_signs = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching vital signs: " . $e->getMessage();
}

// Fetch patients for dropdown
try {
    $patients_stmt = $pdo->query("SELECT id, first_name, last_name, patient_id FROM patients ORDER BY first_name, last_name");
    $patients = $patients_stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching patients: " . $e->getMessage();
    $patients = [];
}

// Handle vital signs entry
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_vitals'])) {
    $patient_id = $_POST['patient_id'];
    $blood_pressure = $_POST['blood_pressure'];
    $heart_rate = $_POST['heart_rate'];
    $temperature = $_POST['temperature'];
    $oxygen_saturation = $_POST['oxygen_saturation'];
    $weight = $_POST['weight'];
    $height = $_POST['height'];
    $respiratory_rate = $_POST['respiratory_rate'];
    $notes = $_POST['notes'];
    
    // Parse blood pressure into systolic/diastolic
    $blood_pressure_systolic = null;
    $blood_pressure_diastolic = null;
    if (!empty($blood_pressure)) {
        $bp_parts = explode('/', $blood_pressure);
        if (count($bp_parts) === 2) {
            $blood_pressure_systolic = intval(trim($bp_parts[0]));
            $blood_pressure_diastolic = intval(trim($bp_parts[1]));
        }
    }
    
    // Calculate BMI
    $bmi = null;
    if ($height > 0 && $weight > 0) {
        $height_in_meters = $height / 100;
        $bmi = round($weight / ($height_in_meters * $height_in_meters), 2);
    }
    
    
   $temperature_celsius = $temperature ? round($temperature, 1) : null;
    
    $recorded_by = $_SESSION['user_id'] ?? 55; // Default to user 55 if not set
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO patient_vitals 
            (patient_id, blood_pressure_systolic, blood_pressure_diastolic, 
             heart_rate, temperature, oxygen_saturation, respiratory_rate, 
             weight, height, bmi, recorded_by, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $patient_id, 
            $blood_pressure_systolic,
            $blood_pressure_diastolic,
            $heart_rate, 
            $temperature_celsius, 
            $oxygen_saturation, 
            $respiratory_rate, 
            $weight, 
            $height, 
            $bmi, 
            $recorded_by,
            $notes
        ]);
        
        $_SESSION['success'] = "Vital signs recorded successfully!";
        header("Location: vitals_history.php");
        exit();
    } catch (PDOException $e) {
        $error_message = "Error recording vital signs: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vital Signs - AuraCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            font-size: 0.875rem;
            margin: 0.5rem 0 0 0;
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
        
        .search-box input, .search-box select {
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
        
        .vital-value {
            font-weight: 600;
            color: #111827;
        }
        
        .vital-normal { color: #10b981; }
        .vital-warning { color: #f59e0b; }
        .vital-danger { color: #ef4444; }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
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
            margin: 2% auto;
            padding: 0;
            border-radius: 0.75rem;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
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
        
        .chart-container {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .chart-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #111827;
        }
        
        .text-muted {
            color: #6b7280;
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
                    <i class="fas fa-heartbeat"></i>
                    Vital Signs
                </h1>
                <p class="page-subtitle">Manage patient vital signs and measurements</p>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Record Vitals
            </button>
        </div>
        
        <div class="filters">
            <div class="search-box">
                <form method="GET">
                    <input type="text" name="search" placeholder="Search by patient name or ID..." 
                           value="<?php echo htmlspecialchars($search_term); ?>">
                </form>
            </div>
            <div class="search-box" style="flex: 0 0 250px;">
                <form method="GET">
                    <select name="patient_id" onchange="this.form.submit()">
                        <option value="">All Patients</option>
                        <?php foreach ($patients as $patient): ?>
                        <option value="<?php echo $patient['id']; ?>" <?php echo $patient_filter == $patient['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
        
         <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-list"></i>
                    Vital Signs History
                </h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Date & Time</th>
                            <th>Blood Pressure</th>
                            <th>Heart Rate</th>
                            <th>Temperature</th>
                            <th>SpO2</th>
                            <th>Resp. Rate</th>
                            <th>Weight</th>
                            <th>Height</th>
                            <th>BMI</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($vital_signs) > 0): ?>
                            <?php foreach ($vital_signs as $vital): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($vital['first_name'] . ' ' . $vital['last_name']); ?></strong><br>
                                    <small class="text-muted">ID: <?php echo htmlspecialchars($vital['patient_id']); ?></small><br>
                                    <small class="text-muted">DOB: <?php echo date('M j, Y', strtotime($vital['date_of_birth'])); ?> • <?php echo htmlspecialchars($vital['gender']); ?></small>
                                </td>
                                <td>
                                    <?php if ($vital['recorded_at']): ?>
                                        <?php echo date('M j, Y g:i A', strtotime($vital['recorded_at'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="vital-value">
                                        <?php 
                                        if ($vital['blood_pressure_systolic'] && $vital['blood_pressure_diastolic']) {
                                            echo htmlspecialchars($vital['blood_pressure_systolic'] . '/' . $vital['blood_pressure_diastolic']);
                                        } else {
                                            echo '<span class="text-muted">N/A</span>';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="vital-value <?php echo getHeartRateClass($vital['heart_rate']); ?>">
                                        <?php echo $vital['heart_rate'] ? $vital['heart_rate'] . ' bpm' : '<span class="text-muted">N/A</span>'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="vital-value <?php echo getTemperatureClass($vital['temperature']); ?>">
                                        <?php 
                                        if ($vital['temperature']) {
                                           echo $vital['temperature'] . '°C';
                                        } else {
                                            echo '<span class="text-muted">N/A</span>';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="vital-value <?php echo getOxygenClass($vital['oxygen_saturation']); ?>">
                                        <?php echo $vital['oxygen_saturation'] ? $vital['oxygen_saturation'] . '%' : '<span class="text-muted">N/A</span>'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="vital-value <?php echo getRespiratoryRateClass($vital['respiratory_rate']); ?>">
                                        <?php echo $vital['respiratory_rate'] ? $vital['respiratory_rate'] . ' rpm' : '<span class="text-muted">N/A</span>'; ?>
                                    </span>
                                </td>
                                <td><?php echo $vital['weight'] ? $vital['weight'] . ' kg' : '<span class="text-muted">N/A</span>'; ?></td>
                                <td><?php echo $vital['height'] ? $vital['height'] . ' cm' : '<span class="text-muted">N/A</span>'; ?></td>
                                <td>
                                    <?php if ($vital['bmi']): ?>
                                        <span class="vital-value <?php echo getBMIClass($vital['bmi']); ?>">
                                            <?php echo $vital['bmi']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($vital['notes'] ?: 'N/A'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" style="text-align: center; padding: 3rem; color: #6b7280;">
                                    <i class="fas fa-heartbeat" style="font-size: 3rem; margin-bottom: 1rem; display: block; color: #d1d5db;"></i>
                                    No vital signs recorded
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if ($patient_filter && count($vital_signs) > 0): ?>
        <div class="chart-container">
            <div class="chart-title">Heart Rate Trend</div>
            <canvas id="heartRateChart" height="100"></canvas>
        </div>
        
        <div class="chart-container">
            <div class="chart-title">Blood Pressure Trend</div>
            <canvas id="bpChart" height="100"></canvas>
        </div>
        <?php endif; ?>
        
       
    </div>
    
    <!-- Add Vitals Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Record Vital Signs</h3>
                <button class="modal-close" onclick="closeAddModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="vitalsForm">
                    <input type="hidden" name="add_vitals" value="1">
                    
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
                            <label for="blood_pressure">Blood Pressure (mmHg):</label>
                            <input type="text" name="blood_pressure" id="blood_pressure" class="form-control" required
                                   placeholder="e.g., 120/80">
                        </div>
                        
                        <div class="form-group">
                            <label for="heart_rate">Heart Rate (bpm):</label>
                            <input type="number" name="heart_rate" id="heart_rate" class="form-control" required
                                   min="30" max="200" step="1">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="temperature">Temperature (°C):</label>
                            <input type="number" name="temperature" id="temperature" class="form-control" required
                                   min="35" max="42" step="0.1">
                        </div>
                        
                        <div class="form-group">
                            <label for="oxygen_saturation">Oxygen Saturation (%):</label>
                            <input type="number" name="oxygen_saturation" id="oxygen_saturation" class="form-control" required
                                   min="70" max="100" step="1">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="respiratory_rate">Respiratory Rate (rpm):</label>
                            <input type="number" name="respiratory_rate" id="respiratory_rate" class="form-control" required
                                   min="8" max="40" step="1">
                        </div>
                        
                        <div class="form-group">
                            <label for="weight">Weight (kg):</label>
                            <input type="number" name="weight" id="weight" class="form-control" required
                                   min="20" max="300" step="0.1">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="height">Height (cm):</label>
                            <input type="number" name="height" id="height" class="form-control" required
                                   min="100" max="250" step="0.1">
                        </div>
                        
                        <div class="form-group">
                            <label for="bmi">BMI (auto-calculated):</label>
                            <input type="text" id="bmi" class="form-control" readonly
                                   placeholder="Will be calculated automatically">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes:</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3"
                                  placeholder="Additional notes..."></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-save"></i> Record Vital Signs
                    </button>
                </form>
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
        
        // Calculate BMI when weight or height changes
        document.getElementById('weight').addEventListener('input', calculateBMI);
        document.getElementById('height').addEventListener('input', calculateBMI);
        
        function calculateBMI() {
            const weight = parseFloat(document.getElementById('weight').value);
            const height = parseFloat(document.getElementById('height').value);
            
            if (weight && height && height > 0) {
                const heightInMeters = height / 100;
                const bmi = (weight / (heightInMeters * heightInMeters)).toFixed(1);
                document.getElementById('bmi').value = bmi;
            } else {
                document.getElementById('bmi').value = '';
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addModal');
            if (event.target == modal) {
                closeAddModal();
            }
        }
        
        <?php if ($patient_filter && count($vital_signs) > 0): ?>
        // Prepare data for charts
        const vitalsData = [
            <?php 
            $reversed_vitals = array_reverse($vital_signs); // Reverse to show chronological order
            foreach ($reversed_vitals as $vital): 
                $bp_systolic = $vital['blood_pressure_systolic'] ?: 0;
                $bp_diastolic = $vital['blood_pressure_diastolic'] ?: 0;
                $heart_rate = $vital['heart_rate'] ?: 0;
            ?>
            {
                date: '<?php echo date('M j', strtotime($vital['recorded_at'])); ?>',
                heartRate: <?php echo $heart_rate; ?>,
                systolic: <?php echo $bp_systolic; ?>,
                diastolic: <?php echo $bp_diastolic; ?>
            },
            <?php endforeach; ?>
        ];
        
        // Create heart rate chart
        const hrCtx = document.getElementById('heartRateChart').getContext('2d');
        const heartRateChart = new Chart(hrCtx, {
            type: 'line',
            data: {
                labels: vitalsData.map(data => data.date),
                datasets: [{
                    label: 'Heart Rate (bpm)',
                    data: vitalsData.map(data => data.heartRate),
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'Heart Rate (bpm)'
                        }
                    }
                }
            }
        });
        
        // Create blood pressure chart
        const bpCtx = document.getElementById('bpChart').getContext('2d');
        const bpChart = new Chart(bpCtx, {
            type: 'line',
            data: {
                labels: vitalsData.map(data => data.date),
                datasets: [
                    {
                        label: 'Systolic (mmHg)',
                        data: vitalsData.map(data => data.systolic),
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.4,
                        fill: false
                    },
                    {
                        label: 'Diastolic (mmHg)',
                        data: vitalsData.map(data => data.diastolic),
                        borderColor: '#9b59b6',
                        backgroundColor: 'rgba(155, 89, 182, 0.1)',
                        tension: 0.4,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'Blood Pressure (mmHg)'
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
<?php 
// Helper functions for vital sign classification
function getHeartRateClass($rate) {
    if (!$rate) return '';
    if ($rate < 60) return 'vital-warning';
    if ($rate > 100) return 'vital-warning';
    return 'vital-normal';
}

function getTemperatureClass($temp) {
    if (!$temp) return '';
    if ($temp < 36.1 || $temp > 37.2) return 'vital-warning';
    return 'vital-normal';
}

function getOxygenClass($oxygen) {
    if (!$oxygen) return '';
    if ($oxygen < 95) return 'vital-warning';
    if ($oxygen < 90) return 'vital-danger';
    return 'vital-normal';
}

function getRespiratoryRateClass($rate) {
    if (!$rate) return '';
    if ($rate < 12 || $rate > 20) return 'vital-warning';
    return 'vital-normal';
}

function getBMIClass($bmi) {
    if (!$bmi) return '';
    if ($bmi < 18.5) return 'vital-warning';
    if ($bmi > 24.9) return 'vital-warning';
    return 'vital-normal';
}

include "includes/footer.php"; 
?>