<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

if (!isset($pdo)) {
    die("Database connection failed.");
}

date_default_timezone_set('Africa/Nairobi');

// Check if user has permission to view audit logs
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: login.php");
    exit();
}

// Verify user has admin privileges
try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !in_array($user['role'], ['Super Admin', 'Admin'])) {
        die("Access denied. Administrator privileges required.");
    }
} catch (PDOException $e) {
    die("Error verifying user permissions: " . $e->getMessage());
}

// Function to add audit log entry
function addAuditLog($pdo, $user_id, $action, $description, $ip_address = null, $user_agent = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent, timestamp) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $action, $description, $ip_address, $user_agent]);
        return true;
    } catch (PDOException $e) {
        error_log("Audit log error: " . $e->getMessage());
        return false;
    }
}

// Create audit_logs table if it doesn't exist
try {
    $pdo->query("
        CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED,
            action VARCHAR(100) NOT NULL,
            description TEXT NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            timestamp DATETIME NOT NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_timestamp (timestamp),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
} catch (PDOException $e) {
    // Table might already exist, continue
}

// Handle filters and pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$filters = [
    'action' => $_GET['action'] ?? '',
    'user_id' => $_GET['user_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Build WHERE clause for filters
$whereConditions = [];
$params = [];

if (!empty($filters['action'])) {
    $whereConditions[] = "al.action = ?";
    $params[] = $filters['action'];
}

if (!empty($filters['user_id'])) {
    $whereConditions[] = "al.user_id = ?";
    $params[] = $filters['user_id'];
}

if (!empty($filters['date_from'])) {
    $whereConditions[] = "DATE(al.timestamp) >= ?";
    $params[] = $filters['date_from'];
}

if (!empty($filters['date_to'])) {
    $whereConditions[] = "DATE(al.timestamp) <= ?";
    $params[] = $filters['date_to'];
}

if (!empty($filters['search'])) {
    $whereConditions[] = "(al.description LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $searchTerm = "%{$filters['search']}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = $whereConditions ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total count for pagination
$countStmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $whereClause
");
$countStmt->execute($params);
$totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalRecords / $limit);

// Get audit logs with pagination
// Get audit logs with pagination
// Build the SQL query with proper parameter handling
$sql = "
    SELECT 
        al.*,
        u.first_name,
        u.last_name,
        u.username,
        u.role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $whereClause
    ORDER BY al.timestamp DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get distinct actions for filter dropdown
$actionsStmt = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action");
$distinctActions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);

// Get users for filter dropdown
$usersStmt = $pdo->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as name, role 
    FROM users 
    WHERE status = 'active' 
    ORDER BY first_name, last_name
");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get login statistics
$loginStats = [];
try {
    // Today's logins
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM audit_logs 
        WHERE action = 'login' 
        AND DATE(timestamp) = CURDATE()
    ");
    $loginStats['today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // This week's logins
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM audit_logs 
        WHERE action = 'login' 
        AND YEARWEEK(timestamp) = YEARWEEK(CURDATE())
    ");
    $loginStats['this_week'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Failed logins (last 7 days)
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM login_attempts 
        WHERE success = 0 
        AND attempt_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $loginStats['failed_logins'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Most active users (last 7 days)
    $stmt = $pdo->query("
        SELECT 
            u.first_name,
            u.last_name,
            u.role,
            COUNT(al.id) as activity_count
        FROM audit_logs al
        JOIN users u ON al.user_id = u.id
        WHERE al.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY al.user_id
        ORDER BY activity_count DESC
        LIMIT 5
    ");
    $loginStats['most_active'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Continue if statistics queries fail
}

// Get failed login attempts
$failedLogins = [];
try {
    $stmt = $pdo->query("
        SELECT 
            username_attempted,
            ip_address,
            attempt_time,
            user_agent
        FROM login_attempts 
        WHERE success = 0 
        ORDER BY attempt_time DESC 
        LIMIT 50
    ");
    $failedLogins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Continue if login_attempts table doesn't exist
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log - System Activities</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .audit-container {
            padding: 30px;
            margin-left: 260px;
            max-width: 100%;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header h1 {
            font-size: 28px;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .stats-grid {
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
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
        }

        .filters-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .filters-card h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 500;
            margin-bottom: 5px;
            color: #2c3e50;
            font-size: 14px;
        }

        .form-control {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }

        .data-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .data-card h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e9ecef;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .action-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .action-login { background: #e8f5e9; color: #388e3c; }
        .action-logout { background: #ffebee; color: #c62828; }
        .action-create { background: #e3f2fd; color: #1976d2; }
        .action-update { background: #fff3e0; color: #ef6c00; }
        .action-delete { background: #fce4ec; color: #c2185b; }
        .action-view { background: #f3e5f5; color: #7b1fa2; }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }

        .pagination-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .pagination-btn:hover:not(:disabled) {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-info {
            color: #7f8c8d;
            font-size: 14px;
        }

        .no-data {
            text-align: center;
            color: #7f8c8d;
            font-style: italic;
            padding: 40px 0;
        }

        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }

        .tab {
            padding: 12px 24px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab.active {
            border-bottom-color: #667eea;
            color: #667eea;
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .ip-address {
            font-family: monospace;
            font-size: 12px;
            color: #666;
        }

        .description-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .audit-container {
                margin-left: 0;
                padding: 15px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 8px 4px;
            }
        }
    </style>
</head>
<body>
    <div class="audit-container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-clipboard-list"></i> System Audit Log</h1>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="exportToCSV()">
                    <i class="fas fa-download"></i> Export CSV
                </button>
                <button class="btn btn-primary" onclick="refreshData()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($totalRecords); ?></div>
                <div class="stat-label">Total Log Entries</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $loginStats['today'] ?? 0; ?></div>
                <div class="stat-label">Logins Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $loginStats['this_week'] ?? 0; ?></div>
                <div class="stat-label">Logins This Week</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $loginStats['failed_logins'] ?? 0; ?></div>
                <div class="stat-label">Failed Logins (7 days)</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" onclick="switchTab('audit-logs')">Audit Logs</div>
            <div class="tab" onclick="switchTab('failed-logins')">Failed Login Attempts</div>
            <div class="tab" onclick="switchTab('statistics')">Statistics</div>
        </div>

        <!-- Audit Logs Tab -->
        <div class="tab-content active" id="audit-logs">
            <!-- Filters -->
            <div class="filters-card">
                <h3><i class="fas fa-filter"></i> Filter Logs</h3>
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label for="action">Action Type</label>
                        <select name="action" id="action" class="form-control">
                            <option value="">All Actions</option>
                            <?php foreach ($distinctActions as $action): ?>
                                <option value="<?php echo htmlspecialchars($action); ?>" 
                                    <?php echo $filters['action'] === $action ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($action); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="user_id">User</label>
                        <select name="user_id" id="user_id" class="form-control">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                    <?php echo $filters['user_id'] == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['name'] . ' (' . $user['role'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_from">Date From</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" 
                               value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_to">Date To</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" 
                               value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="search">Search</label>
                        <input type="text" name="search" id="search" class="form-control" 
                               placeholder="Search descriptions..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <a href="audit_log.php" class="btn btn-secondary" style="margin-top: 5px;">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Audit Logs Table -->
            <div class="data-card">
                <h3><i class="fas fa-list"></i> System Activities</h3>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($auditLogs) > 0): ?>
                                <?php foreach ($auditLogs as $log): ?>
                                    <tr>
                                        <td>
                                            <?php echo date('M j, Y H:i:s', strtotime($log['timestamp'])); ?>
                                        </td>
                                        <td>
                                            <?php if ($log['user_id']): ?>
                                                <div class="user-info">
                                                    <div class="user-avatar">
                                                        <?php 
                                                            $initials = substr($log['first_name'] ?? '', 0, 1) . substr($log['last_name'] ?? '', 0, 1);
                                                            echo strtoupper($initials);
                                                        ?>
                                                    </div>
                                                    <div>
                                                        <div style="font-weight: 500;">
                                                            <?php echo htmlspecialchars(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')); ?>
                                                        </div>
                                                        <div style="font-size: 12px; color: #666;">
                                                            @<?php echo htmlspecialchars($log['username'] ?? 'N/A'); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <em>System</em>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="action-badge action-<?php echo $log['action']; ?>">
                                                <?php echo ucfirst($log['action']); ?>
                                            </span>
                                        </td>
                                        <td class="description-cell" title="<?php echo htmlspecialchars($log['description']); ?>">
                                            <?php echo htmlspecialchars($log['description']); ?>
                                        </td>
                                        <td class="ip-address">
                                            <?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="no-data">
                                        <i class="fas fa-inbox fa-3x" style="margin-bottom: 10px;"></i>
                                        <p>No audit log entries found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <button class="pagination-btn" 
                                onclick="changePage(<?php echo $page - 1; ?>)" 
                                <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                            <i class="fas fa-chevron-left"></i> Previous
                        </button>
                        
                        <span class="pagination-info">
                            Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                            (<?php echo number_format($totalRecords); ?> total records)
                        </span>
                        
                        <button class="pagination-btn" 
                                onclick="changePage(<?php echo $page + 1; ?>)" 
                                <?php echo $page >= $totalPages ? 'disabled' : ''; ?>>
                            Next <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Failed Login Attempts Tab -->
        <div class="tab-content" id="failed-logins">
            <div class="data-card">
                <h3><i class="fas fa-exclamation-triangle"></i> Failed Login Attempts</h3>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Username Attempted</th>
                                <th>IP Address</th>
                                <th>User Agent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($failedLogins) > 0): ?>
                                <?php foreach ($failedLogins as $attempt): ?>
                                    <tr>
                                        <td>
                                            <?php echo date('M j, Y H:i:s', strtotime($attempt['attempt_time'])); ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($attempt['username_attempted']); ?></strong>
                                        </td>
                                        <td class="ip-address">
                                            <?php echo htmlspecialchars($attempt['ip_address'] ?? 'N/A'); ?>
                                        </td>
                                        <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" 
                                            title="<?php echo htmlspecialchars($attempt['user_agent'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($attempt['user_agent'] ?? 'N/A'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="no-data">
                                        <i class="fas fa-shield-alt fa-3x" style="margin-bottom: 10px;"></i>
                                        <p>No failed login attempts found</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Statistics Tab -->
        <div class="tab-content" id="statistics">
            <div class="data-card">
                <h3><i class="fas fa-chart-bar"></i> Most Active Users (Last 7 Days)</h3>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Activity Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($loginStats['most_active'])): ?>
                                <?php foreach ($loginStats['most_active'] as $user): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                                        <td>
                                            <span style="font-weight: 500; color: #667eea;">
                                                <?php echo $user['activity_count']; ?> activities
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="no-data">
                                        <i class="fas fa-users fa-3x" style="margin-bottom: 10px;"></i>
                                        <p>No activity data available</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize date pickers
        flatpickr("#date_from", {
            dateFormat: "Y-m-d",
            maxDate: "today"
        });

        flatpickr("#date_to", {
            dateFormat: "Y-m-d",
            maxDate: "today"
        });

        // Tab switching
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Activate selected tab
            event.target.classList.add('active');
        }

        // Pagination
        function changePage(page) {
            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            window.location.href = url.toString();
        }

        // Export to CSV
        function exportToCSV() {
            // Get current filters
            const params = new URLSearchParams(window.location.search);
            
            // Redirect to export script with current filters
            window.location.href = 'export_audit_logs.php?' + params.toString();
        }

        // Refresh data
        function refreshData() {
            window.location.reload();
        }

        // Auto-refresh every 2 minutes if on first tab
        setInterval(() => {
            if (document.querySelector('.tab.active').textContent === 'Audit Logs') {
                refreshData();
            }
        }, 120000);
    </script>
</body>
</html>