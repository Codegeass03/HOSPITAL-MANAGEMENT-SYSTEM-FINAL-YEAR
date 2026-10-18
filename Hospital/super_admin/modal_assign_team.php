<?php
session_start();
require_once "includes/db_connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$alert_id = $_GET['alert_id'] ?? 0;

// Fetch alert details
$stmt = $pdo->prepare("
    SELECT sa.*, 
           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
           p.age,
           p.gender,
           p.blood_type
    FROM sos_alerts sa
    LEFT JOIN patients p ON sa.patient_id = p.id
    WHERE sa.id = ?
");
$stmt->execute([$alert_id]);
$alert = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch available teams
$teams_stmt = $pdo->query("
    SELECT * FROM emergency_teams 
    WHERE current_status = 'available'
    ORDER BY team_type, team_name
");
$available_teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Response Team | AuraCare HMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-blue: #1e88e5;
            --dark-blue: #1565c0;
            --success-green: #4caf50;
            --alert-red: #e53935;
            --white: #ffffff;
            --border: #e0e0e0;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f0f9ff 0%, #e6f7ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .modal-container {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: white;
            padding: 25px;
            text-align: center;
            position: relative;
        }
        
        .modal-header h2 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .modal-header h2 i {
            font-size: 28px;
        }
        
        .modal-subtitle {
            opacity: 0.9;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .modal-content {
            padding: 30px;
        }
        
        .patient-info {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid var(--border);
        }
        
        .patient-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .patient-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .patient-name {
            font-size: 18px;
            font-weight: 600;
            color: #263238;
            margin-bottom: 4px;
        }
        
        .patient-details {
            color: #546e7a;
            font-size: 13px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #263238;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            color: #263238;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .form-select:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px rgba(30, 136, 229, 0.1);
        }
        
        .form-select option {
            padding: 12px;
        }
        
        .team-option {
            padding: 15px !important;
            border-bottom: 1px solid #eee;
        }
        
        .team-option:last-child {
            border-bottom: none;
        }
        
        .team-option .team-name {
            font-weight: 600;
            color: #263238;
            margin-bottom: 4px;
        }
        
        .team-option .team-details {
            font-size: 13px;
            color: #546e7a;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #546e7a;
            font-size: 16px;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            color: #263238;
            transition: all 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px rgba(30, 136, 229, 0.1);
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            flex: 1;
            padding: 16px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: var(--success-green);
            color: white;
        }
        
        .btn-primary:hover {
            background: #388e3c;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.3);
        }
        
        .btn-secondary {
            background: #f5f5f5;
            color: #546e7a;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        
        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            font-size: 18px;
        }
        
        .close-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }
        
        .no-teams {
            text-align: center;
            padding: 40px 20px;
            color: #546e7a;
        }
        
        .no-teams-icon {
            font-size: 48px;
            color: #cfd8dc;
            margin-bottom: 20px;
        }
        
        .no-teams-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #263238;
        }
        
        @media (max-width: 600px) {
            .modal-container {
                max-width: 100%;
            }
            
            .modal-content {
                padding: 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="modal-container">
        <div class="modal-header">
            <h2><i class="fas fa-ambulance"></i> Assign Response Team</h2>
            <div class="modal-subtitle">Select an available emergency response team</div>
            <button class="close-btn" onclick="window.close()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-content">
            <div class="patient-info">
                <div class="patient-header">
                    <div class="patient-avatar">
                        <?php echo strtoupper(substr($alert['patient_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div class="patient-name"><?php echo htmlspecialchars($alert['patient_name']); ?></div>
                        <div class="patient-details">
                            <span><?php echo $alert['age']; ?> years</span>
                            <span>•</span>
                            <span><?php echo $alert['gender']; ?></span>
                            <span>•</span>
                            <span>Blood: <?php echo $alert['blood_type']; ?></span>
                        </div>
                    </div>
                </div>
                <div style="font-size: 14px; color: #263238;">
                    <strong>Emergency:</strong> <?php echo htmlspecialchars($alert['emergency_type']); ?>
                </div>
            </div>
            
            <form id="assignTeamForm" method="POST" action="sos_portal.php">
                <input type="hidden" name="action" value="assign_team">
                <input type="hidden" name="alert_id" value="<?php echo $alert_id; ?>">
                
                <?php if (!empty($available_teams)): ?>
                    <div class="form-group">
                        <label class="form-label" for="team_id">
                            <i class="fas fa-ambulance"></i> Select Response Team
                        </label>
                        <select class="form-select" id="team_id" name="team_id" required>
                            <option value="">Choose a response team...</option>
                            <?php foreach ($available_teams as $team): ?>
                                <option value="<?php echo $team['id']; ?>" class="team-option">
                                    <div class="team-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                                    <div class="team-details">
                                        <span><?php echo ucfirst($team['team_type']); ?> Team</span>
                                        <span>•</span>
                                        <span><?php echo htmlspecialchars($team['contact_number']); ?></span>
                                        <span>•</span>
                                        <span><?php echo htmlspecialchars($team['vehicle_number']); ?></span>
                                    </div>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="estimated_arrival">
                            <i class="far fa-clock"></i> Estimated Arrival Time
                        </label>
                        <div class="input-group">
                            <i class="fas fa-clock input-icon"></i>
                            <input type="text" 
                                   class="form-input" 
                                   id="estimated_arrival" 
                                   name="estimated_arrival" 
                                   placeholder="e.g., 15 minutes"
                                   required>
                        </div>
                        <div style="font-size: 12px; color: #78909c; margin-top: 8px;">
                            Enter approximate arrival time (e.g., "15 minutes", "30 min")
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="window.close()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Dispatch Team
                        </button>
                    </div>
                <?php else: ?>
                    <div class="no-teams">
                        <i class="fas fa-exclamation-circle no-teams-icon"></i>
                        <div class="no-teams-title">No Teams Available</div>
                        <p style="color: #546e7a; margin-bottom: 20px;">
                            All response teams are currently busy. Please try again later or escalate to manual response.
                        </p>
                        <button type="button" class="btn btn-secondary" onclick="window.close()">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <script>
        document.getElementById('assignTeamForm').addEventListener('submit', function(e) {
            const teamId = document.getElementById('team_id').value;
            const arrivalTime = document.getElementById('estimated_arrival').value;
            
            if (!teamId || !arrivalTime.trim()) {
                e.preventDefault();
                alert('Please select a team and enter estimated arrival time.');
                return false;
            }
            
            // Show confirmation
            if (!confirm('Are you sure you want to dispatch this team? This will mark them as busy.')) {
                e.preventDefault();
                return false;
            }
            
            // If opened in a popup, close after submission
            setTimeout(() => {
                if (window.opener && !window.opener.closed) {
                    window.opener.location.reload();
                }
                window.close();
            }, 1000);
        });
        
        // Focus on first input
        document.addEventListener('DOMContentLoaded', function() {
            const teamSelect = document.getElementById('team_id');
            if (teamSelect) {
                teamSelect.focus();
            }
        });
    </script>
</body>
</html>