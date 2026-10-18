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

$search_results = [];
$search_term = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search'])) {
    $search_term = $_POST['search_term'] ?? '';
    
    if (!empty($search_term)) {
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   (SELECT MAX(recorded_at) FROM patient_vitals WHERE patient_id = p.id) as last_vitals_date
            FROM patients p 
            WHERE p.status = 'active' 
            AND (p.patient_id LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR p.phone LIKE ?)
            ORDER BY p.first_name, p.last_name
            LIMIT 50
        ");
        
        $search_pattern = "%$search_term%";
        $stmt->execute([$search_pattern, $search_pattern, $search_pattern, $search_pattern]);
        $search_results = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Search - AuraCare HMS</title>
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
        
        .search-section {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .search-form {
            display: flex;
            gap: 1rem;
            align-items: end;
        }
        
        .form-group {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-input {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .results-section {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }
        
        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f9fafb;
        }
        
        th {
            padding: 0.875rem 1rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.875rem;
            color: #374151;
        }
        
        tr:hover {
            background: #f9fafb;
        }
        
        .patient-name {
            font-weight: 600;
            color: #111827;
        }
        
        .patient-id {
            color: #6b7280;
            font-size: 0.75rem;
        }
        
        .action-btn {
            padding: 0.375rem 0.75rem;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: #374151;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .action-btn:hover {
            background: #f3f4f6;
        }
        
        .action-btn.primary {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
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
        
        .vital-status {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
        }
        
        .vital-recorded {
            background: #dcfce7;
            color: #166534;
        }
        
        .vital-none {
            background: #f3f4f6;
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
            <h1 class="page-title">
                <i class="fas fa-search"></i>
                Patient Search
            </h1>
            <p class="page-subtitle">Search for patient records to view medical history and create new encounters</p>
        </div>
        
        <div class="search-section">
            <form method="POST" class="search-form">
                <div class="form-group">
                    <label class="form-label">Search Patients</label>
                    <input type="text" name="search_term" class="form-input" 
                           value="<?php echo htmlspecialchars($search_term); ?>"
                           placeholder="Search by Patient ID, Name, or Phone Number..." required>
                </div>
                <button type="submit" name="search" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Search
                </button>
            </form>
        </div>
        
        <?php if ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
        <div class="results-section">
            <div class="section-header">
                <h2 class="section-title">
                    Search Results
                    <?php if (!empty($search_term)): ?>
                    <span style="font-size: 0.875rem; color: #6b7280; font-weight: normal;">
                        - <?php echo count($search_results); ?> patient(s) found for "<?php echo htmlspecialchars($search_term); ?>"
                    </span>
                    <?php endif; ?>
                </h2>
            </div>
            
            <?php if (count($search_results) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Contact</th>
                        <th>Date of Birth</th>
                        <th>Gender</th>
                        <th>Last Vitals</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($search_results as $patient): ?>
                    <tr>
                        <td>
                            <div class="patient-name">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                            </div>
                            <div class="patient-id">
                                ID: <?php echo htmlspecialchars($patient['patient_id']); ?>
                            </div>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($patient['phone']); ?><br>
                            <small style="color: #6b7280;"><?php echo htmlspecialchars($patient['email']); ?></small>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($patient['date_of_birth'])); ?></td>
                        <td><?php echo ucfirst($patient['gender']); ?></td>
                        <td>
                            <?php if ($patient['last_vitals_date']): ?>
                                <span class="vital-status vital-recorded">
                                    <i class="fas fa-heartbeat"></i>
                                    <?php echo date('M j, Y', strtotime($patient['last_vitals_date'])); ?>
                                </span>
                            <?php else: ?>
                                <span class="vital-status vital-none">
                                    <i class="fas fa-times"></i>
                                    No vitals recorded
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="clinical_notes.php?patient_id=<?php echo $patient['id']; ?>" 
                                   class="action-btn primary">
                                    <i class="fas fa-file-medical"></i>
                                    Start Visit
                                </a>
                                <a href="medical_history.php?patient_id=<?php echo $patient['id']; ?>" 
                                   class="action-btn">
                                    <i class="fas fa-history"></i>
                                    History
                                </a>
                                <a href="vitals_history.php?patient_id=<?php echo $patient['id']; ?>" 
                                   class="action-btn">
                                    <i class="fas fa-heartbeat"></i>
                                    Vitals
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>No Patients Found</h3>
                <p>No patients match your search criteria. Try a different search term.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php include "includes/footer.php"; ?>