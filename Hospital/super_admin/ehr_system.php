<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

// Get statistics
$total_records = $pdo->query("SELECT COUNT(*) FROM medical_records")->fetchColumn();
$recent_updates = $pdo->query("SELECT COUNT(*) FROM medical_records WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
$pending_reviews = $pdo->query("SELECT COUNT(*) FROM medical_records WHERE status = 'draft'")->fetchColumn();
$active_patients = $pdo->query("SELECT COUNT(DISTINCT patient_id) FROM medical_records WHERE DATE(record_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();

// Get recent medical records
$records_stmt = $pdo->query("
    SELECT 
        mr.id,
        mr.record_number,
        mr.record_type,
        mr.record_date,
        mr.diagnosis,
        mr.treatment,
        mr.status,
        mr.priority,
        mr.department,
        CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
        p.patient_id,
        p.age,
        p.gender,
        CONCAT(d.first_name, ' ', d.last_name) AS doctor_name
    FROM medical_records mr
    JOIN patients p ON mr.patient_id = p.id
    JOIN users d ON mr.doctor_id = d.id
    ORDER BY mr.record_date DESC, mr.created_at DESC
    LIMIT 20
");
$records = $records_stmt->fetchAll();

// Get messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EHR System - MediCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            background: #cee7ffff;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        .main-content {
            margin-left: 210px;
            background: #f7f9fb;
        }
        
        .ehr-header {
            /* background: linear-gradient(135deg, #012280ff 0%, #122c5eff 100%); */
            padding: 2rem 2.5rem;
            color: #1a1a1a;
        }
        
        .ehr-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0.2rem 0.2rem 0.2rem 0;
        }
        
        .ehr-subtitle {
            font-size: 0.95rem;
            color: #020f13ff;
            margin: 0;
        }
        
        .ehr-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .btn-import {
            background: white;
            color: #1a1a1a;
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .btn-new {
            background: #00b8d4;
            color: white;
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            font-size: 0.875rem;
            box-shadow: 0 2px 8px rgba(0,184,212,0.3);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            padding: 2rem 2.5rem;
            margin-top: -3rem;
            position: relative;
            z-index: 10;
        }
        
        .stat-card {
            background: white;
            padding: 1.8rem;
            border-radius: 0.75rem;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            position: relative;
        }
        
        .stat-icon {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
            font-size: 1.2rem;
        }
        
        .stat-icon.blue { background: #e3f2fd; color: #1976d2; }
        .stat-icon.yellow { background: #fff8e1; color: #f57c00; }
        .stat-icon.orange { background: #ffe0b2; color: #e65100; }
        .stat-icon.green { background: #e8f5e9; color: #388e3c; }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: #666;
            margin: 0.5rem 0 0 0;
        }
        
        .records-section {
            padding: 0 2.5rem 2.5rem 2.5rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1a1a1a;
            margin: 0;
        }
        
        .section-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        .btn-filter, .btn-export {
            background: white;
            border: 1px solid #e0e0e0;
            padding: 0.6rem 1.2rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #333;
        }
        
        .search-box {
            background: #98b4ffff;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .search-input {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 2.5rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            background: white;
            position: relative;
        }
        
        .search-icon {
            position: absolute;
            left: 3.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .records-list {
            background: white;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        
        .records-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .records-table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }
        
        .records-table th {
            padding: 1rem 1.5rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .records-table td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.875rem;
        }
        
        .records-table tr:hover {
            background: #f8f9fa;
        }
        
        .patient-info {
            display: flex;
            flex-direction: column;
        }
        
        .patient-name {
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .patient-id {
            font-size: 0.75rem;
            color: #999;
            margin-top: 0.2rem;
        }
        
        .priority-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .priority-normal { background: #e3f2fd; color: #1976d2; }
        .priority-high { background: #fff3e0; color: #f57c00; }
        .priority-critical { background: #ffebee; color: #c62828; }
        
        .status-badge {
            padding: 0.35rem 0.9rem;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: capitalize;
        }
        
        .status-completed { background: #4caf50; color: white; }
        .status-draft { background: #ffc107; color: #1a1a1a; }
        .status-reviewed { background: #2196f3; color: white; }
        
        .action-btns {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border: 1px solid #e0e0e0;
            background: white;
            border-radius: 0.375rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #666;
            text-decoration: none;
        }
        
        .action-btn:hover {
            background: #f5f5f5;
            color: #333;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin: 1rem 2.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="ehr-header">
            <h1 class="ehr-title">EHR System</h1>
            <p class="ehr-subtitle">Electronic Health Records management</p>
            <div class="ehr-actions">
                <button class="btn-import">
                    <i class="fas fa-download"></i>
                    Import Records
                </button>
                <button class="btn-new" onclick="openAddRecordModal()">
                    <i class="fas fa-plus"></i>
                    New Record
                </button>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-file-medical"></i>
                </div>
                <h2 class="stat-value"><?php echo number_format($total_records); ?></h2>
                <p class="stat-label">Total Records<br>All patient records</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon yellow">
                    <i class="fas fa-clock"></i>
                </div>
                <h2 class="stat-value"><?php echo $recent_updates; ?></h2>
                <p class="stat-label">Recent Updates<br>Updated this week</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-eye"></i>
                </div>
                <h2 class="stat-value"><?php echo $pending_reviews; ?></h2>
                <p class="stat-label">Pending Reviews<br>Require attention</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-user-check"></i>
                </div>
                <h2 class="stat-value"><?php echo $active_patients; ?></h2>
                <p class="stat-label">Active Patients<br>With recent activity</p>
            </div>
        </div>
        
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
        
        <div class="records-section">
            <div class="section-header">
                <h2 class="section-title">Patient Records</h2>
                <div class="section-actions">
                    <button class="btn-filter">
                        <i class="fas fa-filter"></i>
                        Filter
                    </button>
                    <button class="btn-export">
                        <i class="fas fa-file-export"></i>
                        Export
                    </button>
                </div>
            </div>
            
            <div class="search-box">
                <div style="position: relative;">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" placeholder="Search patient records..." id="searchInput">
                </div>
            </div>
            
            <div class="records-list">
                <table class="records-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Record Type</th>
                            <th>Doctor</th>
                            <th>Priority</th>
                            <th>Last Update</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                        <tr>
                            <td>
                                <div class="patient-info">
                                    <span class="patient-name"><?php echo htmlspecialchars($record['patient_name']); ?></span>
                                    <span class="patient-id"><?php echo htmlspecialchars($record['patient_id']); ?> • Age <?php echo $record['age']; ?></span>
                                </div>
                            </td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $record['record_type'])); ?></td>
                            <td>Dr. <?php echo htmlspecialchars($record['doctor_name']); ?></td>
                            <td>
                                <span class="priority-badge priority-<?php echo $record['priority']; ?>">
                                    <?php echo ucfirst($record['priority']); ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d', strtotime($record['record_date'])); ?></td>
                            <td>
                                <div class="action-btns">
                                    <a href="view_medical_record.php?id=<?php echo $record['id']; ?>" class="action-btn" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_medical_record.php?id=<?php echo $record['id']; ?>" class="action-btn" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.records-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        function openAddRecordModal() {
            window.location.href = 'add_medical_record.php';
        }
    </script>
</body>
</html>
<?php include "includes/footer.php"; ?>