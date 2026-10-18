<?php
session_start();
require_once "includes/db_connect.php";
require_once "includes/sidebar.php";
require_once "admin/connection.php";

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user has admin privileges
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!in_array($user['role'], ['Super Admin', 'Admin', 'Department Head'])) {
    header("Location: dashboard.php");
    exit();
}

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new team
    if (isset($_POST['add_team'])) {
        $team_name = $_POST['team_name'];
        $team_type = $_POST['team_type'];
        $department = $_POST['department'] ?? null;
        $contact_number = $_POST['contact_number'] ?? null;
        $vehicle_number = $_POST['vehicle_number'] ?? null;
        $specializations = $_POST['specializations'] ?? null;
        $current_status = 'available';
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO emergency_teams (
                    team_name, team_type, department, 
                    contact_number, vehicle_number, 
                    specializations, current_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $team_name, $team_type, $department,
                $contact_number, $vehicle_number,
                $specializations, $current_status
            ]);
            
            $message = "Team added successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error adding team: " . $e->getMessage();
            $message_type = "error";
        }
    }
    
    // Update team status
    if (isset($_POST['update_status'])) {
        $team_id = $_POST['team_id'];
        $new_status = $_POST['new_status'];
        $current_location = $_POST['current_location'] ?? null;
        
        try {
            $stmt = $pdo->prepare("
                UPDATE emergency_teams 
                SET current_status = ?, 
                    current_location = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$new_status, $current_location, $team_id]);
            
            $message = "Team status updated successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error updating status: " . $e->getMessage();
            $message_type = "error";
        }
    }
    
    // Delete team
    if (isset($_POST['delete_team'])) {
        $team_id = $_POST['team_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM emergency_teams WHERE id = ?");
            $stmt->execute([$team_id]);
            
            $message = "Team deleted successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error deleting team: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Fetch all teams
$teams = [];
try {
    $stmt = $pdo->query("
        SELECT et.*
        FROM emergency_teams et
        ORDER BY et.created_at DESC
    ");
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching teams: " . $e->getMessage();
}

// Get departments for dropdown
$departments = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Continue without departments
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Teams Management | AuraCare HMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f8f9ff 0%, #e6e9ff 100%);
            color: #333;
            min-height: 100vh;
        }

        .dashboard-container {
            padding: 30px;
            margin-left: 260px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eaeaea;
        }

        .page-header h1 {
            color: #2c3e50;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header h1 i {
            color: #f44336;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            color: white;
        }

        .message-box {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            animation: slideDown 0.3s ease;
        }

        .message-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .message-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 1px solid #dee2e6;
        }

        .card-header h2 {
            color: #2c3e50;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            transition: border 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .teams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .team-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #eaeaea;
        }

        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .team-card-header {
            padding: 20px;
            border-bottom: 1px solid #eaeaea;
            position: relative;
        }

        .team-type-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-medical { background: #e3f2fd; color: #1976d2; }
        .badge-paramedic { background: #fff3e0; color: #f57c00; }
        .badge-nursing { background: #f3e5f5; color: #7b1fa2; }
        .badge-security { background: #e8f5e8; color: #388e3c; }

        .team-name {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .team-department {
            color: #6c757d;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .team-card-body {
            padding: 20px;
        }

        .team-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f5f5f5;
        }

        .team-info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .info-label {
            color: #6c757d;
            font-size: 13px;
            font-weight: 500;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 600;
            font-size: 13px;
            text-align: right;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-available { background: #d4edda; color: #155724; }
        .status-busy { background: #fff3cd; color: #856404; }
        .status-off_duty { background: #d1ecf1; color: #0c5460; }
        .status-on_break { background: #e2e3e5; color: #383d41; }

        .team-card-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #eaeaea;
            display: flex;
            gap: 10px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            flex: 1;
            text-align: center;
        }

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
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }

        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            color: #2c3e50;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            color: #6c757d;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close-modal:hover {
            color: #f44336;
        }

        .modal-body {
            padding: 25px;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .no-teams {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .no-teams i {
            font-size: 48px;
            color: #adb5bd;
            margin-bottom: 15px;
        }

        .statistics-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 14px;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                margin-left: 0;
                padding: 15px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .action-buttons {
                width: 100%;
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .teams-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php if (!empty($message)): ?>
        <div class="message-box message-<?php echo $message_type; ?>">
            <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-users-medical"></i> Emergency Teams Management</h1>
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="showAddTeamModal()">
                    <i class="fas fa-plus"></i> Add New Team
                </button>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <?php
        // Get team statistics
        $team_stats = [
            'total' => count($teams),
            'available' => 0,
            'busy' => 0,
            'medical' => 0,
            'paramedic' => 0
        ];
        
        foreach ($teams as $team) {
            if ($team['current_status'] == 'available') $team_stats['available']++;
            if ($team['current_status'] == 'busy') $team_stats['busy']++;
            if ($team['team_type'] == 'medical') $team_stats['medical']++;
            if ($team['team_type'] == 'paramedic') $team_stats['paramedic']++;
        }
        ?>
        
        <div class="statistics-bar">
            <div class="stat-card">
                <div class="stat-value"><?php echo $team_stats['total']; ?></div>
                <div class="stat-label">Total Teams</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $team_stats['available']; ?></div>
                <div class="stat-label">Available Teams</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $team_stats['busy']; ?></div>
                <div class="stat-label">Busy Teams</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $team_stats['paramedic']; ?></div>
                <div class="stat-label">Paramedic Teams</div>
            </div>
        </div>

        <!-- Teams Grid -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> Emergency Teams</h2>
            </div>
            <div class="card-body">
                <?php if (count($teams) > 0): ?>
                    <div class="teams-grid">
                        <?php foreach ($teams as $team): ?>
                            <div class="team-card">
                                <div class="team-card-header">
                                    <span class="team-type-badge badge-<?php echo $team['team_type']; ?>">
                                        <?php echo ucfirst($team['team_type']); ?>
                                    </span>
                                    <div class="team-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                                    <?php if (!empty($team['department'])): ?>
                                        <div class="team-department">
                                            <i class="fas fa-hospital"></i>
                                            <?php echo htmlspecialchars($team['department']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="team-card-body">
                                    <?php if (!empty($team['description'])): ?>
                                        <div class="team-info-row">
                                            <span class="info-label">Description:</span>
                                            <span class="info-value"><?php echo htmlspecialchars(substr($team['description'], 0, 50)) . '...'; ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="team-info-row">
                                        <span class="info-label">Status:</span>
                                        <span class="info-value">
                                            <span class="status-badge status-<?php echo $team['current_status']; ?>">
                                                <?php echo str_replace('_', ' ', ucfirst($team['current_status'])); ?>
                                            </span>
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($team['team_leader_id'])): ?>
                                    <div class="team-info-row">
                                        <span class="info-label">Team Leader ID:</span>
                                        <span class="info-value">#<?php echo $team['team_leader_id']; ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($team['team_members_count'])): ?>
                                    <div class="team-info-row">
                                        <span class="info-label">Members:</span>
                                        <span class="info-value"><?php echo $team['team_members_count']; ?> members</span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($team['contact_number'])): ?>
                                    <div class="team-info-row">
                                        <span class="info-label">Contact:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($team['contact_number']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($team['current_location'])): ?>
                                    <div class="team-info-row">
                                        <span class="info-label">Location:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($team['current_location']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($team['vehicle_number'])): ?>
                                    <div class="team-info-row">
                                        <span class="info-label">Vehicle:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($team['vehicle_number']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($team['specializations'])): ?>
                                    <div class="team-info-row">
                                        <span class="info-label">Specializations:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($team['specializations']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($team['equipment'])): ?>
                                    <div class="team-info-row">
                                        <span class="info-label">Equipment:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($team['equipment']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($team['response_time'])): ?>
                                    <div class="team-info-row">
                                        <span class="info-label">Response Time:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($team['response_time']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="team-card-footer">
                                    <button class="btn btn-sm btn-success" onclick="showStatusModal(<?php echo $team['id']; ?>, '<?php echo htmlspecialchars($team['team_name']); ?>')">
                                        <i class="fas fa-sync-alt"></i> Update Status
                                    </button>
                                    <!-- <button class="btn btn-sm btn-warning" onclick="showEditModal(<?php echo $team['id']; ?>, '<?php echo htmlspecialchars($team['team_name']); ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button> -->
                                    <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $team['id']; ?>, '<?php echo htmlspecialchars(addslashes($team['team_name'])); ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-teams">
                        <i class="fas fa-users-slash"></i>
                        <h3>No Emergency Teams Found</h3>
                        <p>Click "Add New Team" to create your first emergency response team.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Team Modal -->
    <div id="addTeamModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New Emergency Team</h3>
                <button class="close-modal" onclick="closeModal('addTeamModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="team_name">Team Name *</label>
                        <input type="text" id="team_name" name="team_name" class="form-control" required placeholder="e.g., Rapid Response Team A">
                    </div>
                    
                    <div class="form-group">
                        <label for="team_type">Team Type *</label>
                        <select id="team_type" name="team_type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="medical">Medical Team</option>
                            <option value="paramedic">Paramedic Team</option>
                            <option value="nursing">Nursing Team</option>
                            <option value="security">Security Team</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department" class="form-control" list="departments" placeholder="e.g., Emergency Department">
                        <datalist id="departments">
                            <?php foreach ($departments as $dept): ?>
                                <?php if (!empty($dept['department'])): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department']); ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_number">Contact Number</label>
                        <input type="tel" id="contact_number" name="contact_number" class="form-control" placeholder="+254700123456">
                    </div>
                    
                    <div class="form-group">
                        <label for="vehicle_number">Vehicle Number</label>
                        <input type="text" id="vehicle_number" name="vehicle_number" class="form-control" placeholder="AMB-001">
                    </div>
                    
                    <div class="form-group">
                        <label for="specializations">Specializations</label>
                        <textarea id="specializations" name="specializations" class="form-control" placeholder="Cardiac, Trauma, Pediatric, etc." rows="3"></textarea>
                    </div>
                    
                    <div class="form-group" style="margin-top: 30px;">
                        <button type="submit" name="add_team" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-save"></i> Save Team
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-sync-alt"></i> Update Team Status</h3>
                <button class="close-modal" onclick="closeModal('statusModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="statusForm">
                    <input type="hidden" name="team_id" id="status_team_id">
                    
                    <div class="form-group">
                        <label for="new_status">Status *</label>
                        <select id="new_status" name="new_status" class="form-control" required>
                            <option value="available">Available</option>
                            <option value="busy">Busy</option>
                            <option value="on_break">On Break</option>
                            <option value="off_duty">Off Duty</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="current_location">Current Location</label>
                        <input type="text" id="current_location" name="current_location" class="form-control" placeholder="Emergency Department, Ward 3, etc.">
                    </div>
                    
                    <div class="form-group" style="margin-top: 30px;">
                        <button type="submit" name="update_status" class="btn btn-success" style="width: 100%;">
                            <i class="fas fa-check-circle"></i> Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Team Modal -->
    <div id="editTeamModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Team</h3>
                <button class="close-modal" onclick="closeModal('editTeamModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" action="" id="editForm">
                    <input type="hidden" name="team_id" id="edit_team_id">
                    
                    <div class="form-group">
                        <label for="edit_team_name">Team Name *</label>
                        <input type="text" id="edit_team_name" name="team_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_team_type">Team Type *</label>
                        <select id="edit_team_type" name="team_type" class="form-control" required>
                            <option value="medical">Medical Team</option>
                            <option value="paramedic">Paramedic Team</option>
                            <option value="nursing">Nursing Team</option>
                            <option value="security">Security Team</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_department">Department</label>
                        <input type="text" id="edit_department" name="department" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_contact_number">Contact Number</label>
                        <input type="tel" id="edit_contact_number" name="contact_number" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_vehicle_number">Vehicle Number</label>
                        <input type="text" id="edit_vehicle_number" name="vehicle_number" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_specializations">Specializations</label>
                        <textarea id="edit_specializations" name="specializations" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group" style="margin-top: 30px;">
                        <button type="submit" name="edit_team" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-save"></i> Update Team
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h3>
                <button class="close-modal" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage" style="margin-bottom: 25px; font-size: 16px;"></p>
                <form method="POST" action="" id="deleteForm">
                    <input type="hidden" name="team_id" id="delete_team_id">
                    <div style="display: flex; gap: 15px;">
                        <button type="button" class="btn btn-secondary" style="flex: 1;" onclick="closeModal('deleteModal')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" name="delete_team" class="btn btn-danger" style="flex: 1;">
                            <i class="fas fa-trash"></i> Delete Team
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function showAddTeamModal() {
            document.getElementById('addTeamModal').style.display = 'flex';
        }

        function showStatusModal(teamId, teamName) {
            document.getElementById('status_team_id').value = teamId;
            document.getElementById('statusModal').style.display = 'flex';
            
            // Set modal title
            const modalTitle = document.querySelector('#statusModal h3');
            modalTitle.innerHTML = `<i class="fas fa-sync-alt"></i> Update Status: ${teamName}`;
        }

        function showEditModal(teamId, teamName) {
            // Fetch team data via AJAX
            fetch(`get_team.php?id=${teamId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_team_id').value = teamId;
                        document.getElementById('edit_team_name').value = data.team.team_name;
                        document.getElementById('edit_team_type').value = data.team.team_type;
                        document.getElementById('edit_department').value = data.team.department || '';
                        document.getElementById('edit_contact_number').value = data.team.contact_number || '';
                        document.getElementById('edit_vehicle_number').value = data.team.vehicle_number || '';
                        document.getElementById('edit_specializations').value = data.team.specializations || '';
                        
                        document.getElementById('editTeamModal').style.display = 'flex';
                        
                        // Set modal title
                        const modalTitle = document.querySelector('#editTeamModal h3');
                        modalTitle.innerHTML = `<i class="fas fa-edit"></i> Edit Team: ${teamName}`;
                    } else {
                        alert('Error loading team data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading team data');
                });
        }

        function confirmDelete(teamId, teamName) {
            document.getElementById('delete_team_id').value = teamId;
            document.getElementById('deleteMessage').innerHTML = 
                `Are you sure you want to delete the team "<strong>${teamName}</strong>"? This action cannot be undone.`;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            }
        }

        // Auto-refresh page every 60 seconds to update statuses
        setTimeout(() => {
            location.reload();
        }, 60000);
                                    
        // Handle edit form submission via AJAX
document.getElementById('editForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('edit_team', '1');
    
    fetch('edit_team.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeModal('editTeamModal');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the team');
    });
});

    </script>
</body>
</html>