<?php
session_start();  
// if(!isset($_SESSION["super_admin"])) {
//     header("Location: ../index.php");
//     exit();
// }

include "includes/db_connect.php";
include "includes/sidebar.php";

// Get success or error messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;

// Clear messages after displaying
unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Patient Management - MediCare HMS</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    /* Alert Messages */
    .alert {
      padding: 1rem 1.5rem;
      border-radius: 0.5rem;
      margin-bottom: 1.5rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .alert-success {
      background-color: #dcfce7;
      color: #166534;
      border: 1px solid #bbf7d0;
    }

    .alert-error {
      background-color: #fee2e2;
      color: #dc2626;
      border: 1px solid #fecaca;
    }

    body {
      margin: 0;
      display: flex;
      font-family: Arial, sans-serif;
    }

    /* Main content wrapper */
    .main-wrapper {
      margin-left: 10px;
      flex: 1;
      display: flex;
      flex-direction: column;
      background-color: #f8fafc;
      min-height: 100vh;
    }

    /* Your existing main-content style */
    .main-content {
      padding: 2rem;
      flex: 1;
      background-color: #f8fafc;
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

    .add-staff-btn {
      background: linear-gradient(135deg, #0ea5e9, #0284c7);
      color: white;
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: 0.5rem;
      text-decoration: none;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.2s;
      box-shadow: 0 2px 4px rgba(14, 165, 233, 0.2);
      cursor: pointer;
    }

    .add-staff-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
    }

    .search-filters {
      display: flex;
      gap: 1rem;
      margin-bottom: 2rem;
      flex-wrap: wrap;
      align-items: center;
    }

    .search-box {
      position: relative;
      flex: 1;
      min-width: 300px;
    }

    .search-box input {
      width: 100%;
      padding: 0.75rem 1rem 0.75rem 2.5rem;
      border: 1px solid #e2e8f0;
      border-radius: 0.5rem;
      font-size: 0.875rem;
      background: white;
      transition: all 0.2s;
    }

    .search-box input:focus {
      outline: none;
      border-color: #0ea5e9;
      box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
    }

    .search-box .search-icon {
      position: absolute;
      left: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
    }

    .department-filter {
      padding: 0.75rem 1rem;
      border: 1px solid #e2e8f0;
      border-radius: 0.5rem;
      background: white;
      font-size: 0.875rem;
      color: #475569;
      min-width: 150px;
    }

    /* Sidebar list reset */
    .sidebar ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .sidebar li {
      margin-bottom: 0.5rem;
    }

    .sidebar a {
      display: block;
      text-decoration: none;
      color: white;
      padding: 0.15rem;
      border-radius: 0.375rem;
      transition: background 0.2s ease;
    }

    .sidebar a:hover {
      background-color: #334155;
      color: #f8fafc;
    }

    .staff-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 1.5rem;
    }

    .staff-card {
      background: white;
      border-radius: 0.75rem;
      padding: 1.5rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      transition: all 0.2s;
      border: 1px solid #f1f5f9;
      position: relative;
    }

    .staff-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .staff-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 1rem;
    }

    .staff-avatar {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: linear-gradient(135deg, #0ea5e9, #0284c7);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: white;
      font-weight: 600;
      margin-bottom: 1rem;
    }

    .staff-info h3 {
      margin: 0 0 0.25rem 0;
      color: #1e293b;
      font-size: 1.125rem;
      font-weight: 600;
    }

    .staff-role {
      color: #64748b;
      font-size: 0.875rem;
      margin-bottom: 1rem;
    }

    .status-badge {
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.025em;
    }

    .status-active {
      background-color: #dcfce7;
      color: #166534;
    }

    .status-inactive {
      background-color: #f3f4f6;
      color: #6b7280;
    }

    .status-pending {
      background-color: #fef3c7;
      color: #92400e;
    }

    .staff-details {
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      margin-bottom: 1rem;
    }

    .detail-item {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: #64748b;
      font-size: 0.875rem;
    }

    .detail-icon {
      width: 16px;
      color: #94a3b8;
    }

    .staff-stats {
      display: flex;
      gap: 1rem;
      margin-bottom: 1rem;
      padding-top: 1rem;
      border-top: 1px solid #f1f5f9;
    }

    .stat-item {
      display: flex;
      align-items: center;
      gap: 0.375rem;
      color: #64748b;
      font-size: 0.75rem;
    }

    .stat-icon {
      color: #fbbf24;
    }

    .staff-actions {
      display: flex;
      gap: 0.5rem;
      margin-top: 1rem;
      flex-wrap: wrap;
    }

    .action-btn {
      flex: 1;
      padding: 0.5rem;
      border: 1px solid #e2e8f0;
      background: white;
      border-radius: 0.375rem;
      color: #64748b;
      text-decoration: none;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.25rem;
      font-size: 0.875rem;
      transition: all 0.2s;
      min-width: 60px;
    }

    .action-btn:hover {
      background: #f8fafc;
      color: #334155;
    }

    .action-btn-danger {
      color: #ef4444;
      border-color: #fecaca;
    }

    .action-btn-danger:hover {
      background: #fef2f2;
      color: #dc2626;
      border-color: #fca5a5;
    }

    .action-btn-success {
      color: #10b981;
      border-color: #a7f3d0;
    }

    .action-btn-success:hover {
      background: #d1fae5;
      color: #047857;
      border-color: #6ee7b7;
    }

    .action-btn-warning {
      color: #f59e0b;
      border-color: #fde68a;
    }

    .action-btn-warning:hover {
      background: #fef3c7;
      color: #92400e;
      border-color: #fcd34d;
    }

    .action-btn-info {
      color: #3b82f6;
      border-color: #bfdbfe;
    }

    .action-btn-info:hover {
      background: #dbeafe;
      color: #1d4ed8;
      border-color: #93c5fd;
    }

    .login-status {
      position: absolute;
      top: 1.5rem;
      right: 1.5rem;
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 0.25rem;
    }

    .login-badge {
      padding: 0.25rem 0.5rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 500;
    }

    .login-active {
      background: #d1fae5;
      color: #065f46;
    }

    .login-inactive {
      background: #f3f4f6;
      color: #6b7280;
    }

    .login-new {
      background: #fef3c7;
      color: #92400e;
    }

    .login-expired {
      background: #fee2e2;
      color: #991b1b;
    }

    .quick-action-btn {
      background: #10b981;
      color: white;
      border: none;
      padding: 0.25rem 0.75rem;
      border-radius: 0.375rem;
      font-size: 0.75rem;
      cursor: pointer;
      transition: all 0.2s;
    }

    .quick-action-btn:hover {
      background: #059669;
    }

    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }

    .modal.show {
      display: flex;
    }

    .modal-content {
      background: white;
      border-radius: 0.75rem;
      width: 90%;
      max-width: 500px;
      max-height: 90vh;
      overflow-y: auto;
    }

    .modal-header {
      padding: 1.5rem 1.5rem 0 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .modal-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: #1e293b;
      margin: 0;
    }

    .modal-close {
      background: none;
      border: none;
      font-size: 1.5rem;
      color: #64748b;
      cursor: pointer;
    }

    .modal-body {
      padding: 1.5rem;
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .form-group {
      display: flex;
      flex-direction: column;
    }

    .form-group.full-width {
      grid-column: 1 / -1;
    }

    .form-label {
      font-size: 0.875rem;
      font-weight: 500;
      color: #374151;
      margin-bottom: 0.5rem;
    }

    .form-input, .form-select, .form-textarea {
      padding: 0.75rem;
      border: 1px solid #d1d5db;
      border-radius: 0.5rem;
      font-size: 0.875rem;
      transition: border-color 0.2s;
    }

    .form-input:focus, .form-select:focus, .form-textarea:focus {
      outline: none;
      border-color: #0ea5e9;
      box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
    }

    .form-textarea {
      resize: vertical;
      min-height: 80px;
    }

    .modal-footer {
      padding: 0 1.5rem 1.5rem 1.5rem;
      display: flex;
      gap: 0.75rem;
      justify-content: flex-end;
    }

    .btn-secondary {
      padding: 0.75rem 1.5rem;
      border: 1px solid #d1d5db;
      background: white;
      color: #374151;
      border-radius: 0.5rem;
      text-decoration: none;
      font-weight: 500;
      transition: all 0.2s;
    }

    .btn-secondary:hover {
      background: #f9fafb;
    }

    .btn-primary {
      padding: 0.75rem 1.5rem;
      background: linear-gradient(135deg, #0ea5e9, #0284c7);
      color: white;
      border: none;
      border-radius: 0.5rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-primary:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
    }

    .no-patients {
      grid-column: 1 / -1;
      text-align: center;
      padding: 3rem;
      color: #64748b;
    }

    /* Filter buttons */
    .filter-buttons {
      display: flex;
      gap: 0.5rem;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
      padding: 0.75rem;
      background: white;
      border-radius: 0.75rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .filter-btn {
      padding: 0.5rem 1rem;
      border: 1px solid #e2e8f0;
      background: white;
      border-radius: 0.5rem;
      color: #64748b;
      font-size: 0.875rem;
      cursor: pointer;
      transition: all 0.2s;
      white-space: nowrap;
    }

    .filter-btn.active {
      background: #0ea5e9;
      color: white;
      border-color: #0ea5e9;
    }

    .filter-btn:hover {
      background: #f8fafc;
    }

    .filter-btn.active:hover {
      background: #0284c7;
    }

    /* Login status indicators */
    .login-status-badge {
      display: flex;
      align-items: center;
      gap: 0.25rem;
      font-size: 0.75rem;
    }

    /* Stats summary */
    .stats-summary {
      display: flex;
      gap: 1rem;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
    }

    .stat-summary-card {
      flex: 1;
      min-width: 150px;
      background: white;
      padding: 1rem;
      border-radius: 0.75rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      text-align: center;
    }

    .stat-summary-number {
      font-size: 1.5rem;
      font-weight: 700;
      color: #0ea5e9;
      margin-bottom: 0.25rem;
    }

    .stat-summary-label {
      font-size: 0.875rem;
      color: #64748b;
    }

    @media (max-width: 1024px) {
      .main-wrapper {
        margin-left: 0;
      }
      
      .main-content {
        padding: 1rem;
      }
      
      .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
      }
      
      .add-staff-btn {
        width: 100%;
        justify-content: center;
      }
      
      .staff-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      }
      
      .staff-actions {
        flex-wrap: nowrap;
      }
      
      .action-btn {
        min-width: 50px;
        padding: 0.4rem;
      }
      
      .filter-buttons {
        justify-content: center;
      }
      
      .filter-btn {
        flex: 1;
        min-width: 120px;
        text-align: center;
      }
      
      .action-text {
        display: none;
      }
      
      .stats-summary {
        justify-content: center;
      }
      
      .stat-summary-card {
        min-width: 120px;
      }
    }

    @media (max-width: 768px) {
      .staff-grid {
        grid-template-columns: 1fr;
      }
      
      .staff-card {
        padding: 1rem;
      }
      
      .staff-actions {
        flex-direction: column;
      }
      
      .action-btn {
        width: 100%;
      }
      
      .search-filters {
        flex-direction: column;
      }
      
      .search-box {
        min-width: 100%;
      }
      
      .filter-buttons {
        overflow-x: auto;
        flex-wrap: nowrap;
        justify-content: flex-start;
        padding-bottom: 0.5rem;
      }
      
      .filter-btn {
        flex: none;
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
      }
      
      .login-status {
        position: relative;
        top: 0;
        right: 0;
        margin-bottom: 1rem;
        align-items: flex-start;
      }
      
      .staff-header {
        flex-direction: column;
        align-items: flex-start;
      }
    }

    @media (max-width: 480px) {
      .staff-avatar {
        width: 50px;
        height: 50px;
        font-size: 1.25rem;
      }
      
      .page-title {
        font-size: 1.5rem;
      }
      
      .stat-summary-card {
        min-width: 100px;
        padding: 0.75rem;
      }
      
      .stat-summary-number {
        font-size: 1.25rem;
      }
      
      .staff-details {
        font-size: 0.8rem;
      }
    }
  </style>
</head>
<body>
<div class="main-wrapper">
  <div class="main-content">
    <!-- Alert Messages -->
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
          <i class="fas fa-user-injured"></i>
          Patient Management
        </h1>
        <p class="page-subtitle">Manage patient records and enable online access</p>
      </div>
      <button class="add-staff-btn" onclick="window.location.href='patients.php'">
        <i class="fas fa-plus"></i>
        Add New Patient
      </button>
    </div>

    <!-- Patient Statistics -->
    <div class="stats-summary">
      <?php
      // Fetch statistics
      try {
        $total_patients = $pdo->query("SELECT COUNT(*) as count FROM patients")->fetch()['count'];
        $with_login = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'Patient'")->fetch()['count'];
        $without_login = $total_patients - $with_login;
        $active_patients = $pdo->query("SELECT COUNT(*) as count FROM patients WHERE status = 'active'")->fetch()['count'];
        $inactive_patients = $pdo->query("SELECT COUNT(*) as count FROM patients WHERE status = 'inactive'")->fetch()['count'];
      } catch (PDOException $e) {
        $total_patients = $with_login = $without_login = $active_patients = $inactive_patients = 0;
      }
      ?>
      <div class="stat-summary-card">
        <div class="stat-summary-number"><?php echo $total_patients; ?></div>
        <div class="stat-summary-label">Total Patients</div>
      </div>
      <div class="stat-summary-card">
        <div class="stat-summary-number" style="color: #10b981;"><?php echo $with_login; ?></div>
        <div class="stat-summary-label">With Login</div>
      </div>
      <div class="stat-summary-card">
        <div class="stat-summary-number" style="color: #f59e0b;"><?php echo $without_login; ?></div>
        <div class="stat-summary-label">Without Login</div>
      </div>
      <div class="stat-summary-card">
        <div class="stat-summary-number" style="color: #166534;"><?php echo $active_patients; ?></div>
        <div class="stat-summary-label">Active</div>
      </div>
      <div class="stat-summary-card">
        <div class="stat-summary-number" style="color: #dc2626;"><?php echo $inactive_patients; ?></div>
        <div class="stat-summary-label">Inactive</div>
      </div>
    </div>

    <!-- Filter Buttons -->
    <div class="filter-buttons">
      <button class="filter-btn active" onclick="filterPatients('all')">All Patients</button>
      <button class="filter-btn" onclick="filterPatients('with-login')">With Login Access</button>
      <button class="filter-btn" onclick="filterPatients('has-logged-in')">Has Logged In</button>
      <button class="filter-btn" onclick="filterPatients('never-logged-in')">Never Logged In</button>
      <button class="filter-btn" onclick="filterPatients('no-login')">Without Login</button>
      <button class="filter-btn" onclick="filterPatients('active')">Active</button>
      <button class="filter-btn" onclick="filterPatients('inactive')">Inactive</button>
    </div>

    <div class="search-filters">
      <div class="search-box">
        <i class="fas fa-search search-icon"></i>
        <input type="text" placeholder="Search patient by name, ID, phone, or email..." id="searchInput">
      </div>
    </div>

    <div class="staff-grid">
      <?php
      // Fetch patients with detailed login information
      try {
       // Replace the current query with this one:
$stmt = $pdo->query("
  SELECT 
    p.*,
    u.id as user_id,
    u.username,
    u.status as user_status,
    u.last_login,
    u.created_at as user_created_at,
    COUNT(a.id) as appointment_count,
    MAX(a.appointment_date) as last_appointment,
    CASE 
      WHEN u.last_login IS NOT NULL THEN 'has-logged-in'
      WHEN u.id IS NOT NULL THEN 'setup-not-logged'
      ELSE 'no-login'
    END as login_status
  FROM patients p
  LEFT JOIN users u ON (p.patient_id = u.id OR p.email = u.email OR p.phone = u.phone) AND u.role = 'Patient'
  LEFT JOIN appointments a ON p.id = a.patient_id
  GROUP BY p.id
  ORDER BY p.created_at DESC
");
        $patients = $stmt->fetchAll();
        
        if (empty($patients)) {
          echo '<div class="no-patients">
                  <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                  <h3>No Patients Found</h3>
                  <p>Get started by adding your first patient to the system.</p>
                  <button class="add-staff-btn" onclick="window.location.href=\'add_patient.php\'" style="margin-top: 1rem;">
                    <i class="fas fa-plus"></i> Add First Patient
                  </button>
                </div>';
        }
        
        foreach($patients as $patient):
          $initials = strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1));
          $age = $patient['age'] ?? 'N/A';
          $has_login = !empty($patient['user_id']);
          $has_logged_in = !empty($patient['last_login']);
          $login_status = $patient['login_status'];
          
          // Determine login badge type
          $login_badge_class = 'login-inactive';
          $login_badge_text = 'No Login';
          $login_icon = 'fa-times-circle';
          
          if ($login_status === 'has-logged-in') {
            $login_badge_class = 'login-active';
            $login_badge_text = 'Logged In';
            $login_icon = 'fa-check-circle';
          } elseif ($login_status === 'setup-not-logged') {
            $login_badge_class = 'login-new';
            $login_badge_text = 'Setup - Not Used';
            $login_icon = 'fa-exclamation-circle';
          }
          
          $last_login = $patient['last_login'] ? date('M d, Y h:i A', strtotime($patient['last_login'])) : 'Never';
          $password_set_at = $patient['user_created_at'] ? date('M d, Y', strtotime($patient['user_created_at'])) : 'Not set';
          $appointment_count = $patient['appointment_count'];
          $last_appointment = $patient['last_appointment'] ? date('M d, Y', strtotime($patient['last_appointment'])) : 'No visits';
          
          // Determine button text and action
          if ($login_status === 'has-logged-in') {
            $login_button_text = 'Reset Password';
            $login_button_title = 'Reset Password';
            $login_button_action = 'reset_patient_password.php?patient_id=' . $patient['id'];
            $login_button_class = 'action-btn-info';
            $login_button_icon = 'fa-sync-alt';
            $login_confirmation_msg = 'Reset password for ' . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . '? A temporary password will be generated and emailed.';
          } elseif ($login_status === 'setup-not-logged') {
            $login_button_text = 'Reset Password';
            $login_button_title = 'Reset Password (Never logged in)';
            $login_button_action = 'reset_patient_password.php?patient_id=' . $patient['id'];
            $login_button_class = 'action-btn-warning';
            $login_button_icon = 'fa-sync-alt';
            $login_confirmation_msg = 'Reset password for ' . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . '? They have setup access but never logged in.';
          } else {
            $login_button_text = 'Setup Login';
            $login_button_title = 'Setup Login Access';
            $login_button_action = 'setup_patient_login.php?patient_id=' . $patient['id'];
            $login_button_class = 'action-btn-success';
            $login_button_icon = 'fa-key';
            $login_confirmation_msg = 'Setup login access for ' . htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) . '?';
          }
      ?>
        <div class="staff-card" 
     data-has-login="<?php echo $has_login ? 'yes' : 'no'; ?>" 
     data-login-status="<?php echo $login_status; ?>"
     data-status="<?php echo $patient['status'] ?? 'active'; ?>">
          <div class="staff-header">
            <div class="staff-avatar"><?php echo $initials; ?></div>
            <div class="login-status">
              <span class="login-badge <?php echo $login_badge_class; ?>">
                <i class="fas <?php echo $login_icon; ?>"></i> <?php echo $login_badge_text; ?>
              </span>
              <?php if ($has_login): ?>
                <small style="color: #64748b; font-size: 0.75rem;">
                  <?php if ($has_logged_in): ?>
                    Last: <?php echo $last_login; ?>
                  <?php else: ?>
                    Setup: <?php echo $password_set_at; ?>
                  <?php endif; ?>
                </small>
              <?php endif; ?>
            </div>
          </div>
          <div class="staff-info">
            <h3><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h3>
            <div class="staff-role">
              ID: <?php echo htmlspecialchars($patient['patient_id']); ?>
              <?php if ($patient['user_id']): ?>
                <span style="color: #10b981; font-size: 0.75rem;">
                  • <i class="fas fa-user-circle"></i> Online Access
                </span>
              <?php endif; ?>
            </div>
          </div>
          <div class="staff-details">
            <div class="detail-item">
              <i class="fas fa-user detail-icon"></i>
              <span>Age: <?php echo $age; ?> • <?php echo htmlspecialchars($patient['gender'] ?? 'Not specified'); ?></span>
            </div>
            <div class="detail-item">
              <i class="fas fa-phone detail-icon"></i>
              <span><?php echo htmlspecialchars($patient['phone'] ?? 'No phone'); ?></span>
            </div>
            <div class="detail-item">
              <i class="fas fa-envelope detail-icon"></i>
              <span>
                <?php if (!empty($patient['email'])): ?>
                  <a href="mailto:<?php echo htmlspecialchars($patient['email']); ?>" style="color: #64748b; text-decoration: none;">
                    <?php echo htmlspecialchars($patient['email']); ?>
                  </a>
                <?php else: ?>
                  No email provided
                <?php endif; ?>
              </span>
            </div>
            <div class="detail-item">
              <i class="fas fa-calendar-check detail-icon"></i>
              <span>Visits: <?php echo $appointment_count; ?> • Last: <?php echo $last_appointment; ?></span>
            </div>
          </div>
          
          <?php if (!$has_login && !empty($patient['email']) && !empty($patient['phone'])): ?>
            <div class="quick-setup" style="margin-bottom: 1rem; padding: 0.75rem; background: #f0f9ff; border-radius: 0.5rem; border: 1px solid #e0f2fe;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                  <strong style="color: #0369a1; font-size: 0.875rem;">Setup Online Access</strong>
                  <p style="color: #64748b; font-size: 0.75rem; margin: 0.25rem 0 0 0;">
                    Enable patient portal access
                  </p>
                </div>
                <a href="setup_patient_login.php?patient_id=<?php echo $patient['id']; ?>" 
                   class="quick-action-btn">
                  <i class="fas fa-key"></i> Setup
                </a>
              </div>
            </div>
          <?php endif; ?>
          
          <div class="staff-actions">
            <a href="edit_patient.php?id=<?php echo $patient['id']; ?>" class="action-btn action-btn-warning" title="Edit Patient">
              <i class="fas fa-edit"></i>
              <span class="action-text">Edit</span>
            </a>
            
            <a href="<?php echo $login_button_action; ?>" 
               class="action-btn <?php echo $login_button_class; ?>" 
               title="<?php echo $login_button_title; ?>"
               onclick="return confirm('<?php echo $login_confirmation_msg; ?>');">
              <i class="fas <?php echo $login_button_icon; ?>"></i>
              <span class="action-text"><?php echo $login_button_text; ?></span>
            </a>
            
            <a href="view_patient.php?id=<?php echo $patient['id']; ?>" class="action-btn" title="View Details">
              <i class="fas fa-eye"></i>
              <span class="action-text">View</span>
            </a>
            
            <a href="delete_patient.php?id=<?php echo $patient['id']; ?>" 
               class="action-btn action-btn-danger" 
               title="Delete Patient"
               onclick="return confirm('Are you sure you want to delete this patient? This action cannot be undone.');">
              <i class="fas fa-trash"></i>
              <span class="action-text">Delete</span>
            </a>
          </div>
        </div>
      <?php 
        endforeach;
      } catch (PDOException $e) {
        echo '<div class="alert alert-error">Error loading patients: ' . htmlspecialchars($e->getMessage()) . '</div>';
      }
      ?>
    </div>
  </div>
</div>

<script>
// Search functionality
document.getElementById('searchInput').addEventListener('input', function() {
  const searchTerm = this.value.toLowerCase();
  const patientCards = document.querySelectorAll('.staff-card');
  
  patientCards.forEach(card => {
    const name = card.querySelector('h3').textContent.toLowerCase();
    const patientId = card.querySelector('.staff-role').textContent.toLowerCase();
    const phoneElement = card.querySelector('.detail-item:nth-child(2) span');
    const emailElement = card.querySelector('.detail-item:nth-child(3) span, .detail-item:nth-child(3) a');
    
    const phone = phoneElement ? phoneElement.textContent.toLowerCase() : '';
    let email = '';
    if (emailElement) {
      email = emailElement.textContent.toLowerCase();
      if (emailElement.tagName === 'A') {
        email += ' ' + emailElement.getAttribute('href').toLowerCase();
      }
    }
    
    if (name.includes(searchTerm) || 
        patientId.includes(searchTerm) || 
        phone.includes(searchTerm) || 
        email.includes(searchTerm)) {
      card.style.display = 'block';
    } else {
      card.style.display = 'none';
    }
  });
});

// Enhanced Filter functionality
function filterPatients(filter) {
  const patientCards = document.querySelectorAll('.staff-card');
  const filterButtons = document.querySelectorAll('.filter-btn');
  
  // Update active button
  filterButtons.forEach(btn => {
    btn.classList.remove('active');
    // Get the button's display text
    const buttonText = btn.textContent.trim();
    // Get the display name for the current filter
    const filterDisplayName = getFilterDisplayName(filter);
    
    // If this button's text matches the filter's display name, make it active
    if (buttonText === filterDisplayName) {
      btn.classList.add('active');
    }
  });
  
  // Store active filter in localStorage for persistence
  localStorage.setItem('activePatientFilter', filter);
  
  patientCards.forEach(card => {
    const hasLogin = card.getAttribute('data-has-login');
    const loginStatus = card.getAttribute('data-login-status');
    const status = card.getAttribute('data-status');
    
    switch(filter) {
      case 'all':
        card.style.display = 'block';
        break;
      case 'with-login':
        card.style.display = hasLogin === 'yes' ? 'block' : 'none';
        break;
      case 'has-logged-in':
        card.style.display = loginStatus === 'has-logged-in' ? 'block' : 'none';
        break;
case 'never-logged-in':
  card.style.display =
    (loginStatus === 'setup-not-logged' || loginStatus === 'no-login')
      ? 'block'
      : 'none';
  break;

      case 'no-login':
        card.style.display = loginStatus === 'no-login' ? 'block' : 'none';
        break;
      case 'active':
  card.style.display = status === 'Active' ? 'block' : 'none';
  break;
case 'inactive':
  card.style.display = status === 'Deceased' ? 'block' : 'none';
  break;

      default:
        card.style.display = 'block';
    }
  });
}

function getFilterDisplayName(filter) {
  const filterNames = {
    'all': 'All Patients',
    'with-login': 'With Login Access',
    'has-logged-in': 'Has Logged In',
    'never-logged-in': 'Never Logged In',
    'no-login': 'Without Login',
    'active': 'Active',
    'inactive': 'Inactive'
  };
  return filterNames[filter] || filter;
}

// Mobile responsive adjustments
function handleResize() {
  const width = window.innerWidth;
  const actionTexts = document.querySelectorAll('.action-text');
  
  if (width < 768) {
    actionTexts.forEach(text => {
      text.style.display = 'none';
    });
  } else {
    actionTexts.forEach(text => {
      text.style.display = 'inline';
    });
  }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
  // Handle responsive design
  handleResize();
  window.addEventListener('resize', handleResize);
  
  // Set up quick action buttons
  const quickSetupButtons = document.querySelectorAll('.quick-action-btn');
  quickSetupButtons.forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const patientId = this.getAttribute('href').split('=')[1];
      window.location.href = `setup_patient_login.php?patient_id=${patientId}`;
    });
  });
  
  // Restore active filter from localStorage
  const savedFilter = localStorage.getItem('activePatientFilter');
  if (savedFilter) {
    filterPatients(savedFilter);
  }
  
  // Add keyboard shortcuts
  document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + F focuses search
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
      e.preventDefault();
      document.getElementById('searchInput').focus();
    }
    
    // Escape clears search
    if (e.key === 'Escape') {
      document.getElementById('searchInput').value = '';
      filterPatients('all');
    }
  });
  
  // Auto-clear search when changing filters
  const filterButtons = document.querySelectorAll('.filter-btn');
  filterButtons.forEach(btn => {
    btn.addEventListener('click', function() {
      document.getElementById('searchInput').value = '';
    });
  });
  
  // Highlight patients with login access
  setTimeout(() => {
    document.querySelectorAll('[data-login-status="has-logged-in"]').forEach(card => {
      card.style.borderLeft = '4px solid #10b981';
    });
  }, 100);
});
</script>
</body>
</html>
<?php include "includes/footer.php"; ?>