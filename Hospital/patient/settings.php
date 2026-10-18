<?php
session_start();
include "includes/db_connect.php";


// Get current user data
$user_id = $_SESSION['user_id'] ?? 1; // Replace with actual session user ID
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $username = $_POST['username'];
        $email = $_POST['email'];
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $phone = $_POST['phone'];
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, last_name = ?, phone = ? WHERE id = ?");
            $stmt->execute([$username, $email, $first_name, $last_name, $phone, $user_id]);
            $_SESSION['success'] = "Profile updated successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating profile: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (!password_verify($current_password, $user['password_hash'])) {
            $_SESSION['error'] = "Current password is incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['error'] = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $_SESSION['error'] = "New password must be at least 6 characters long.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            $_SESSION['success'] = "Password changed successfully!";
        }
    }
    
    if (isset($_POST['update_appearance'])) {
        // Update appearance settings
        $theme = $_POST['theme'];
        $sidebar_collapsed = isset($_POST['sidebar_collapsed']) ? 1 : 0;
        $compact_mode = isset($_POST['compact_mode']) ? 1 : 0;
        
        // Store in session (you might want to store in database for persistence)
        $_SESSION['theme'] = $theme;
        $_SESSION['sidebar_collapsed'] = $sidebar_collapsed;
        $_SESSION['compact_mode'] = $compact_mode;
        
        $_SESSION['success'] = "Appearance settings updated!";
    }
    
    header("Location: settings.php");
    exit();
}
?>

<style>
.settings-container {
    max-width: 1000px;
    margin: 0 auto;
}

.settings-tabs {
    display: flex;
    background: white;
    border-radius: 0.75rem 0.75rem 0 0;
    border-bottom: 1px solid #e5e7eb;
    overflow-x: auto;
}

.settings-tab {
    padding: 1rem 1.5rem;
    border: none;
    background: none;
    font-weight: 800;
    font-size: 28px;
    color: #6b7280;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
    white-space: nowrap;
}

.settings-tab:hover {
    color: #374151;
    background: #f9fafb;
}

.settings-tab.active {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
}

.settings-content {
    background: white;
    border-radius: 0 0 0.75rem 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.settings-panel {
    display: none;
    padding: 2rem;
}

.settings-panel.active {
    display: block;
}

.settings-section {
    margin-bottom: 2rem;
}

.settings-section:last-child {
    margin-bottom: 0;
}

.section-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #f3f4f6;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-label {
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.form-input, .form-select {
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.form-input:focus, .form-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e5e7eb;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 0.5rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
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

.btn-secondary {
    background: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
}

/* Appearance Settings */
.theme-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.theme-option {
    border: 2px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.theme-option:hover {
    border-color: #9ca3af;
}

.theme-option.selected {
    border-color: #3b82f6;
    background: #f0f9ff;
}

.theme-preview {
    width: 100%;
    height: 80px;
    border-radius: 0.5rem;
    margin-bottom: 0.5rem;
    border: 1px solid #e5e7eb;
}

.theme-light .theme-preview {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
}

.theme-dark .theme-preview {
    background: linear-gradient(135deg, #1f2937 0%, #374151 100%);
}

.theme-blue .theme-preview {
    background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
}

.theme-green .theme-preview {
    background: linear-gradient(135deg, #065f46 0%, #10b981 100%);
}

.theme-name {
    font-weight: 500;
    color: #374151;
}

/* Toggle Switches */
.toggle-group {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 0;
    border-bottom: 1px solid #f3f4f6;
}

.toggle-label {
    font-weight: 500;
    color: #374151;
}

.toggle-description {
    font-size: 0.875rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #d1d5db;
    transition: .4s;
    border-radius: 24px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background-color: #3b82f6;
}

input:checked + .toggle-slider:before {
    transform: translateX(26px);
}

/* Danger Zone */
.danger-zone {
    border: 1px solid #fecaca;
    background: #fef2f2;
    border-radius: 0.75rem;
    padding: 1.5rem;
}

.danger-title {
    color: #dc2626;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.danger-description {
    color: #b91c1c;
    font-size: 0.875rem;
    margin-bottom: 1rem;
}

.btn-danger {
    background: #dc2626;
    color: white;
}

.btn-danger:hover {
    background: #b91c1c;
}

/* Alert Styles */
.alert {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.alert-error {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

/* Theme Variables */
:root {
    --primary-color: #3b82f6;
    --secondary-color: #6b7280;
    --background-color: #f8fafc;
    --surface-color: #ffffff;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --border-color: #e5e7eb;
}

/* Dark Theme */
[data-theme="dark"] {
    --primary-color: #60a5fa;
    --secondary-color: #9ca3af;
    --background-color: #111827;
    --surface-color: #1f2937;
    --text-primary: #f9fafb;
    --text-secondary: #d1d5db;
    --border-color: #374151;
}

/* Blue Theme */
[data-theme="blue"] {
    --primary-color: #1e40af;
    --secondary-color: #60a5fa;
    --background-color: #dbeafe;
    --surface-color: #ffffff;
    --text-primary: #1e3a8a;
    --text-secondary: #374151;
    --border-color: #93c5fd;
}

/* Green Theme */
[data-theme="green"] {
    --primary-color: #065f46;
    --secondary-color: #10b981;
    --background-color: #ecfdf5;
    --surface-color: #ffffff;
    --text-primary: #064e3b;
    --text-secondary: #374151;
    --border-color: #a7f3d0;
}

/* Apply theme variables */
body {
    background-color: var(--background-color);
    color: var(--text-primary);
}

.main-content {
    background-color: var(--background-color);
}

.card, .stat-card, .ward-card, .bed-card, .settings-content {
    background-color: var(--surface-color);
    border-color: var(--border-color);
    color: var(--text-primary);
}

.btn-primary {
    background-color: var(--primary-color);
}

.text-muted {
    color: var(--text-secondary) !important;
}

/* Responsive */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .settings-tabs {
        flex-wrap: wrap;
    }
    
    .settings-tab {
        flex: 1;
        min-width: 120px;
        text-align: center;
    }
}
</style>

<div class="main-content">
    <?php
    $success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
    $error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
    unset($_SESSION['success'], $_SESSION['error']);
    ?>

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
                <i class="fas fa-cog"></i>
                Settings
            </h1>
            <p class="page-subtitle">
                Manage your account settings and preferences
            </p>
        </div>
    </div>

    <div class="settings-container">
        <div class="settings-tabs">
            <button class="settings-tab active" data-tab="profile">
                <i class="fas fa-user"></i>
                Profile
            </button>
            <button class="settings-tab" data-tab="security">
                <i class="fas fa-shield-alt"></i>
                Security
            </button>
            <!-- <button class="settings-tab" data-tab="appearance">
                <i class="fas fa-palette"></i>
                Appearance
            </button>
            <button class="settings-tab" data-tab="notifications">
                <i class="fas fa-bell"></i>
                Notifications -->
            <!-- </button> -->
        </div>

        <div class="settings-content">
            <!-- Profile Settings -->
            <div class="settings-panel active" id="profile-panel">
                <form method="POST">
                    <div class="settings-section">
                        <h3 class="section-title">Personal Information</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Username *</label>
                                <input type="text" name="username" class="form-input" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email Address *</label>
                                <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-input" value="<?php echo htmlspecialchars($user['first_name']); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-input" value="<?php echo htmlspecialchars($user['last_name']); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone']); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Role</label>
                                <input type="text" class="form-input" value="<?php echo htmlspecialchars($user['role']); ?>" disabled>
                                <small style="color: #6b7280; font-size: 0.875rem;">Role cannot be changed</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Security Settings -->
            <div class="settings-panel" id="security-panel">
                <form method="POST">
                    <div class="settings-section">
                        <h3 class="section-title">Change Password</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Current Password *</label>
                                <input type="password" name="current_password" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">New Password *</label>
                                <input type="password" name="new_password" class="form-input" required>
                                <small style="color: #6b7280; font-size: 0.875rem;">Minimum 6 characters</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm New Password *</label>
                                <input type="password" name="confirm_password" class="form-input" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-key"></i>
                            Change Password
                        </button>
                    </div>
                </form>

                <div class="settings-section">
                    <h3 class="section-title">Login Activity</h3>
                    <div style="background: #f8fafc; padding: 1rem; border-radius: 0.5rem;">
                        <p style="margin: 0; color: #6b7280;">
                            <i class="fas fa-info-circle"></i>
                            Last login: <?php echo date('F j, Y \a\t g:i A', strtotime($user['last_login'])); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Appearance Settings -->
            <div class="settings-panel" id="appearance-panel">
                <form method="POST">
                    <div class="settings-section">
                        <h3 class="section-title">Theme</h3>
                        <div class="theme-options">
                            <div class="theme-option theme-light selected" data-theme="light">
                                <div class="theme-preview"></div>
                                <div class="theme-name">Light</div>
                            </div>
                            <div class="theme-option theme-dark" data-theme="dark">
                                <div class="theme-preview"></div>
                                <div class="theme-name">Dark</div>
                            </div>
                            <div class="theme-option theme-blue" data-theme="blue">
                                <div class="theme-preview"></div>
                                <div class="theme-name">Blue</div>
                            </div>
                            <div class="theme-option theme-green" data-theme="green">
                                <div class="theme-preview"></div>
                                <div class="theme-name">Green</div>
                            </div>
                        </div>
                        <input type="hidden" name="theme" id="selectedTheme" value="light">
                    </div>

                    <div class="settings-section">
                        <h3 class="section-title">Layout</h3>
                        <div class="toggle-group">
                            <div>
                                <div class="toggle-label">Collapsed Sidebar</div>
                                <div class="toggle-description">Show sidebar in collapsed mode by default</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="sidebar_collapsed" value="1">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="toggle-group" style="border-bottom: none;">
                            <div>
                                <div class="toggle-label">Compact Mode</div>
                                <div class="toggle-description">Use compact spacing for lists and tables</div>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="compact_mode" value="1">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_appearance" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save Appearance Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- Notifications Settings -->
            <div class="settings-panel" id="notifications-panel">
                <div class="settings-section">
                    <h3 class="section-title">Email Notifications</h3>
                    <div class="toggle-group">
                        <div>
                            <div class="toggle-label">Appointment Reminders</div>
                            <div class="toggle-description">Receive email reminders for upcoming appointments</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="toggle-group">
                        <div>
                            <div class="toggle-label">Lab Results</div>
                            <div class="toggle-description">Get notified when lab results are ready</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                    <div class="toggle-group" style="border-bottom: none;">
                        <div>
                            <div class="toggle-label">System Updates</div>
                            <div class="toggle-description">Receive notifications about system maintenance and updates</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="settings-section">
                    <h3 class="section-title">Push Notifications</h3>
                    <div class="toggle-group" style="border-bottom: none;">
                        <div>
                            <div class="toggle-label">Enable Push Notifications</div>
                            <div class="toggle-description">Receive real-time notifications in your browser</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Save Notification Settings
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Tab switching functionality
document.querySelectorAll('.settings-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        // Remove active class from all tabs and panels
        document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
        
        // Add active class to clicked tab and corresponding panel
        tab.classList.add('active');
        const tabId = tab.getAttribute('data-tab');
        document.getElementById(`${tabId}-panel`).classList.add('active');
    });
});

// Theme selection
document.querySelectorAll('.theme-option').forEach(option => {
    option.addEventListener('click', () => {
        document.querySelectorAll('.theme-option').forEach(opt => opt.classList.remove('selected'));
        option.classList.add('selected');
        document.getElementById('selectedTheme').value = option.getAttribute('data-theme');
    });
});

// Initialize theme from session/local storage
const savedTheme = '<?php echo $_SESSION['theme'] ?? 'light'; ?>';
if (savedTheme) {
    const themeOption = document.querySelector(`.theme-option[data-theme="${savedTheme}"]`);
    if (themeOption) {
        themeOption.click();
    }
}

// Initialize toggle states from session
document.querySelector('input[name="sidebar_collapsed"]').checked = <?php echo $_SESSION['sidebar_collapsed'] ?? 'false'; ?>;
document.querySelector('input[name="compact_mode"]').checked = <?php echo $_SESSION['compact_mode'] ?? 'false'; ?>;

// Apply saved theme on page load
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    
    // Apply compact mode if enabled
    if (localStorage.getItem('compactMode') === 'true') {
        document.body.classList.add('compact-mode');
    }
    
    // Apply sidebar state
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        document.body.classList.add('sidebar-collapsed');
    }
});
</script>

<?php include "includes/footer.php"; ?>

<aside class="sidebar">
  <div class="sidebar-header">
    <div class="logo">
      <div class="logo-icon">
        <i class="fas fa-heartbeat"></i>
      </div>
      <div class="logo-text">
        <h2>AuraCare HMS</h2>
      
      </div>
    </div>
    <button class="sidebar-toggle" id="sidebarToggle">
      <i class="fas fa-bars"></i>
    </button>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section">
      <div class="section-label">Main Menu</div>
      <ul class="sidebar-menu">
        <li>
          <a href="dashboard.php" class="menu-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
            <!-- <span class="menu-badge">New</span> -->
          </a>
        </li>
      </ul>
    </div>



    <div class="nav-section">
      <div class="section-label">Patient Care</div>
      <ul class="sidebar-menu"> 
        <li>
          <a href="add_new_patients.php" class="menu-item">
            <i class="fas fa-user-injured"></i>
            <span>Patient Management</span>
          </a>
        </li>
        
        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-calendar-check"></i>
            <span>Appointments</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="appointment_scheduling.php"><i class="fas fa-plus-circle"></i>Schedule Appointment</a></li>
            <li><a href="calendar_view.php"><i class="fas fa-calendar-week"></i>Calendar View</a></li>
            <li><a href="appointment_requests.php"><i class="fas fa-clock"></i>Requests & Approvals</a></li>
          </ul>
        </li>

        <li class="has-submenu">
          <a href="#" class="menu-item submenu-toggle">
            <i class="fas fa-file-medical"></i>
            <span>EHR System</span>
            <i class="fas fa-chevron-down dropdown-arrow"></i>
          </a>
          <ul class="submenu">
            <li><a href="ehr_dashboard.php"><i class="fas fa-tachometer-alt"></i>EHR Dashboard</a></li>
            <li><a href="patient_medical_records.php"><i class="fas fa-search"></i>Medical Records</a></li>
            <li><a href="prescriptions.php"><i class="fas fa-prescription"></i>MedicationS</a></li>
            <li><a href="medical_history.php"><i class="fas fa-history"></i>Medical History</a></li>
            <li><a href="vitals_history.php"><i class="fas fa-heartbeat"></i>Vitals History</a></li>
          </ul>
        </li>
      </ul>
    </div>

    

    <div class="nav-section">
      <div class="section-label">Operations</div>
      <ul class="sidebar-menu">

        <li>
          <a href="reports.php" class="menu-item">
            <i class="fas fa-chart-line"></i>
            <span>Reports</span>
          </a>
        </li>
        
       
      </ul>
    </div>

    <div class="nav-section">
      <div class="section-label">System</div>
      <ul class="sidebar-menu">
        <li>
          <a href="settings.php" class="menu-item">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
          </a>
        </li>
        <li>
          <a href="logout.php" class="menu-item logout-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
          </a>
        </li>
      </ul>
    </div>
  </nav>

  <div class="sidebar-footer">
    <div class="user-profile">
      <div class="user-avatar">
        <i class="fas fa-user-shield"></i>
      </div>
      <div class="user-info">
        <span class="user-name">Patient Dashboard</span>
       
      </div>
    </div>
  </div>
</aside>

<style>
/* Sidebar Base Styles */
.sidebar {
  position: fixed;
  left: 0;
  top: 0;
  width: 290px;
  height: 100vh;
  background: linear-gradient(180deg, #0c4da2 0%, #07377c 100%);
  color: white;
  overflow-y: auto;
  overflow-x: hidden;
  box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
  z-index: 800;
  transition: all 0.3s ease;
  display: flex;
  flex-direction: column;
}

/* Compact Sidebar */
.sidebar.compact {
  width: 90px;
}

.sidebar.compact .logo-text,
.sidebar.compact .section-label,
.sidebar.compact .menu-item span,
.sidebar.compact .user-info,
.sidebar.compact .menu-badge,
.sidebar.compact .dropdown-arrow {
  display: none !important;
}

.sidebar.compact .submenu {
  display: none !important;
}

/* Custom Scrollbar */
.sidebar::-webkit-scrollbar {
  width: 6px;
}

.sidebar::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 3px;
}

.sidebar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 3px;
  transition: background 0.3s ease;
}

.sidebar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}

/* Sidebar Header */
.sidebar-header {
  padding: 1.5rem 1.5rem 1rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.05);
  position: sticky;
  top: 0;
  z-index: 10;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.logo {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

.logo-icon {
  width: 40px;
  height: 45px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.4rem;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  flex-shrink: 0;
}

.logo-text h2 {
  margin: 0;
  font-size: 1.6rem;
  font-weight: 900;
  color: white;
  line-height: 1.5;
  letter-spacing: 0.8px;
}

.logo-text small {
  display: block;
  font-size: 1.2rem;
  color: rgba(255, 255, 255, 0.9);
  font-weight: 500;
  margin-top: 0.8rem;
  letter-spacing: 0.3px;
}

.sidebar-toggle {
  background: rgba(255, 255, 255, 0.1);
  border: none;
  color: white;
  width: 35px;
  height: 35px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 1rem;
}

.sidebar-toggle:hover {
  background: rgba(255, 255, 255, 0.2);
  transform: rotate(90deg);
}

/* Navigation */
.sidebar-nav {
  flex: 1;
  padding: 1rem 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.nav-section {
  margin-bottom: 1rem;
}

.section-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: rgba(255, 255, 255, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  padding: 0.5rem 1.5rem 0.25rem;
  margin-bottom: 0.25rem;
}

/* Menu List */
.sidebar-menu {
  list-style: none;
  padding: 0;
  margin: 0;
}

.sidebar-menu li {
  margin: 0;
  position: relative;
}

/* Menu Item Styles */
.menu-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1.5rem;
  color: rgba(255, 255, 255, 0.9);
  text-decoration: none;
  transition: all 0.3s ease;
  font-size: 0.9rem;
  font-weight: 500;
  border-left: 3px solid transparent;
  position: relative;
}

.menu-item:hover {
  background: rgba(255, 255, 255, 0.12);
  border-left-color: #4dabf7;
  color: white;
  padding-left: 1.75rem;
}

.menu-item.active {
  background: rgba(255, 255, 255, 0.15);
  border-left-color: #51cf66;
  color: white;
  font-weight: 600;
}

.menu-item i:first-child {
  font-size: 1.1rem;
  width: 20px;
  text-align: center;
  flex-shrink: 0;
}

.menu-item span {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.menu-badge {
  background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
  color: white;
  padding: 0.2rem 0.6rem;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 600;
  min-width: 20px;
  text-align: center;
}

/* Submenu Styles */
.has-submenu > .menu-item {
  position: relative;
}

.dropdown-arrow {
  transition: transform 0.3s ease;
  font-size: 0.8rem;
  color: rgba(255, 255, 255, 0.7);
}

.has-submenu.active .dropdown-arrow {
  transform: rotate(180deg);
  color: white;
}

.submenu {
  list-style: none;
  padding: 0;
  margin: 0;
  background: rgba(0, 0, 0, 0.2);
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.has-submenu.active .submenu {
  max-height: 500px;
}

.submenu li a {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.75rem 1.5rem 0.75rem 3.5rem;
  color: rgba(255, 255, 255, 0.8);
  text-decoration: none;
  transition: all 0.3s ease;
  font-size: 0.85rem;
  border-left: 3px solid transparent;
}

.submenu li a:hover {
  background: rgba(255, 255, 255, 0.08);
  border-left-color: #ffd43b;
  color: white;
  padding-left: 4rem;
}

.submenu li a i {
  font-size: 0.9rem;
  width: 16px;
  text-align: center;
}

/* Logout Item */
.logout-item {
  color: #ff6b6b !important;
}

.logout-item:hover {
  background: rgba(255, 107, 107, 0.15) !important;
  border-left-color: #ff6b6b !important;
  color: #ff6b6b !important;
}

/* Sidebar Footer */
.sidebar-footer {
  padding: 1.5rem;
  border-top: 1px solid rgba(255, 255, 255, 0.15);
  background: rgba(255, 255, 255, 0.05);
  margin-top: auto;
}

.user-profile {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.user-avatar {
  width: 45px;
  height: 45px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
  flex-shrink: 0;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.user-info {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.user-name {
  font-size: 0.9rem;
  font-weight: 600;
  color: white;
  margin-bottom: 0.1rem;
}

.user-role {
  font-size: 0.75rem;
  color: rgba(255, 255, 255, 0.7);
}

/* Main Content Adjustment */
.main-content {
  margin-left: 300px;
  min-height: 100vh;
  padding: 2rem;
  background-color: #f8f9fa;
  transition: margin-left 0.3s ease;
}

.main-content.expanded {
  margin-left: 80px;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .sidebar {
    width: 80px;
    transform: translateX(-100%);
  }
  
  .sidebar.mobile-open {
    transform: translateX(0);
  }
  
  .main-content {
    margin-left: 0;
  }
  
  .mobile-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
  }
  
  .mobile-overlay.active {
    display: block;
  }
}

@media (max-width: 768px) {
  .main-content {
    padding: 1rem;
  }
}

/* Animation for menu items */
@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(-10px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.sidebar-menu li {
  animation: slideIn 0.3s ease forwards;
}

.sidebar-menu li:nth-child(1) { animation-delay: 0.05s; }
.sidebar-menu li:nth-child(2) { animation-delay: 0.1s; }
.sidebar-menu li:nth-child(3) { animation-delay: 0.15s; }
.sidebar-menu li:nth-child(4) { animation-delay: 0.2s; }
.sidebar-menu li:nth-child(5) { animation-delay: 0.25s; }

/* Enhanced hover effects */
.menu-item::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  height: 100%;
  width: 0;
  background: linear-gradient(90deg, rgba(255,255,255,0.1), transparent);
  transition: width 0.3s ease;
}

.menu-item:hover::before {
  width: 100%;
}

/* Status indicators */
.menu-item.active::after {
  content: '';
  position: absolute;
  right: 1rem;
  top: 50%;
  transform: translateY(-50%);
  width: 6px;
  height: 6px;
  background: #51cf66;
  border-radius: 50%;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% { opacity: 1; }
  50% { opacity: 0.5; }
  100% { opacity: 1; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mainContent = document.querySelector('.main-content');
    const currentPath = window.location.pathname.split('/').pop();
    const mobileOverlay = document.createElement('div');
    
    mobileOverlay.className = 'mobile-overlay';
    document.body.appendChild(mobileOverlay);

    // Toggle sidebar compact mode
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('compact');
        mainContent.classList.toggle('expanded');
        
        // Update toggle icon
        const icon = this.querySelector('i');
        if (sidebar.classList.contains('compact')) {
            icon.className = 'fas fa-chevron-right';
        } else {
            icon.className = 'fas fa-bars';
        }
    });

    // Mobile overlay click to close sidebar
    mobileOverlay.addEventListener('click', function() {
        sidebar.classList.remove('mobile-open');
        this.classList.remove('active');
    });

    // Handle dynamic 'active' class on page load
    document.querySelectorAll('.menu-item').forEach(item => {
        const href = item.getAttribute('href');
        if (href && href !== '#' && currentPath === href.split('/').pop()) {
            // Remove active class from all items
            document.querySelectorAll('.menu-item').forEach(i => i.classList.remove('active'));
            document.querySelectorAll('.has-submenu').forEach(i => i.classList.remove('active'));
            
            // Add active class to current item
            item.classList.add('active');
            
            // If it's in a submenu, activate parent
            const parentSubmenu = item.closest('.has-submenu');
            if (parentSubmenu) {
                parentSubmenu.classList.add('active');
            }
        }
    });

    // Handle Submenu Toggle
    document.querySelectorAll('.submenu-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const parentLi = this.closest('.has-submenu');
            
            // Close other submenus
            document.querySelectorAll('.has-submenu.active').forEach(item => {
                if (item !== parentLi) {
                    item.classList.remove('active');
                }
            });
            
            // Toggle current submenu
            parentLi.classList.toggle('active');
        });
    });

    // Close submenus when clicking outside on mobile
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024 && !sidebar.contains(e.target)) {
            document.querySelectorAll('.has-submenu.active').forEach(item => {
                item.classList.remove('active');
            });
        }
    });

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            sidebar.classList.remove('mobile-open');
            mobileOverlay.classList.remove('active');
        }
    });

    // Add smooth loading animation
    setTimeout(() => {
        sidebar.style.opacity = '1';
        sidebar.style.transform = 'translateX(0)';
    }, 100);
});

// Mobile menu toggle function
function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.mobile-overlay');
    
    sidebar.classList.toggle('mobile-open');
    overlay.classList.toggle('active');
}

// Initialize sidebar with fade-in effect
window.addEventListener('load', function() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.style.opacity = '0';
    sidebar.style.transform = 'translateX(-20px)';
    
    setTimeout(() => {
        sidebar.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        sidebar.style.opacity = '1';
        sidebar.style.transform = 'translateX(0)';
    }, 300);
});
</script>