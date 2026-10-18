<?php
session_start();  
// if(!isset($_SESSION["super_admin"])) {
//     header("Location: ../index.php");
//     exit();
// }

include "includes/db_connect.php";
include "includes/sidebar.php";
?>

<style>
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
</style>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Management</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
     <style>
    body {
      margin: 0;
      display: flex;
      font-family: Arial, sans-serif;
    }

    /* Main content wrapper */
    .main-wrapper {
      margin-left: 250px;
      flex: 1;
      display: flex;
      flex-direction: column;
      background-color: #f8fafc;
      min-height: 100vh;
    }

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
  margin-bottom: 0.7rem;   /* adds space between items */
}

.sidebar a {
  display: block;
  text-decoration: none;   /* removes underline */
  color: white;            
  padding: 0.25rem;   /* adds spacing around text */
  border-radius: 0.35rem; /* rounded corners on hover */
  transition: background 0.2s ease;
}

.sidebar a:hover {
  background-color: #334155; /* subtle hover effect */
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
      background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: #64748b;
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

    .status-leave {
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
    }

    .action-btn:hover {
      background: #f8fafc;
      color: #334155;
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
  </style>
</head>
<body>
<div class="main-content">
  <?php
// Get success or error messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;

// Clear messages after displaying
unset($_SESSION['success'], $_SESSION['error']);
?>

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
        <i class="fas fa-user-md"></i>
        Staff Management
      </h1>
      <p class="page-subtitle">Manage doctors, nurses and hospital staff</p>
    </div>
 
   <button class="add-staff-btn" onclick="window.location.href='add_staff.php'">
  <i class="fas fa-plus"></i>
  Add Staff Member
</button>
  </div>

  <div class="search-filters">
    <div class="search-box">
      <i class="fas fa-search search-icon"></i>
      <input type="text" placeholder="Search staff by name, department or role..." id="searchInput">
    </div>
<select class="department-filter" id="departmentFilter">
  <option value="">All Departments</option>
  <option value="general care">General Care</option>
  <option value="cardiology">Cardiology</option>
  <option value="neurology">Neurology</option>
  <option value="emergency">Emergency</option>
  <option value="orthopedics">Orthopedics</option>
  <option value="nursing">Nursing</option>
  <option value="pediatrics">Pediatrics</option>
  <option value="pharmacy">Pharmacy</option>
  <option value="lab">Lab</option>
  <option value="accounts">Finance</option>
  <option value="operations">Operations</option>
  <option value="hr">HR</option>
</select>
  </div>

  <div class="staff-grid">
  <?php
  // Fetches actual staff from database
  $stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
   $stmt = $pdo->prepare("
    SELECT * FROM users 
    WHERE role NOT IN ('Patient', 'patient') 
    ORDER BY created_at DESC
  ");
  $stmt->execute();
  $staff_members = $stmt->fetchAll();
  
  
  foreach($staff_members as $staff):
    $initials = strtoupper(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1));
  ?>
    <div class="staff-card">
      <div class="staff-header">
        <div class="staff-avatar"><?php echo $initials; ?></div>
        <span class="status-badge status-<?php echo $staff['status']; ?>">
          <?php echo ucfirst($staff['status']); ?>
        </span>
      </div>
      <div class="staff-info">
        <h3><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></h3>
        <div class="staff-role"><?php echo htmlspecialchars($staff['role']); ?></div>
      </div>
      <div class="staff-details">
        <div class="detail-item">
          <i class="fas fa-building detail-icon"></i>
          <span><?php echo htmlspecialchars($staff['department']); ?></span>
        </div>
        <div class="detail-item">
          <i class="fas fa-envelope detail-icon"></i>
          <span><?php echo htmlspecialchars($staff['email']); ?></span>
        </div>
        <div class="detail-item">
          <i class="fas fa-phone detail-icon"></i>
          <span><?php echo htmlspecialchars($staff['phone']); ?></span>
        </div>
      </div>
     <div class="staff-actions">
  <a href="edit_user.php?id=<?php echo $staff['id']; ?>" class="action-btn" title="Edit User">
    <i class="fas fa-edit"></i>
  </a>
  <a href="tel:<?php echo $staff['phone']; ?>" class="action-btn" title="Call">
    <i class="fas fa-phone"></i>
  </a>
  <a href="mailto:<?php echo $staff['email']; ?>" class="action-btn" title="Email">
    <i class="fas fa-envelope"></i>
  </a>
  <a href="delete_user.php?id=<?php echo $staff['id']; ?>" 
     class="action-btn action-btn-danger" 
     title="Delete User"
     onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
    <i class="fas fa-trash"></i>
  </a>
</div>

<style>
.action-btn-danger {
  color: #ef4444 !important;
  border-color: #fecaca !important;
}

.action-btn-danger:hover {
  background: #fef2f2 !important;
  color: #dc2626 !important;
  border-color: #fca5a5 !important;
}
</style>
    </div>
  <?php endforeach; ?>
</div>

<!-- Add Staff Modal/Registrationform -->
<div class="modal" id="addStaffModal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 class="modal-title">Add New Staff Member</h2>
      <button class="modal-close" onclick="closeAddStaffModal()">&times;</button>
    </div>
    <form id="addStaffForm" action="process_add_user.php" method="POST">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">First Name *</label>
            <input type="text" name="first_name" class="form-input" placeholder="Enter first name" required>
          </div>
          <div class="form-group">
            <label class="form-label">Last Name *</label>
            <input type="text" name="last_name" class="form-input" placeholder="Enter last name" required>
          </div>
          <div class="form-group">
            <label class="form-label">Username *</label>
            <input type="text" name="username" class="form-input" placeholder="Enter username" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email *</label>
            <input type="email" name="email" class="form-input" placeholder="Enter email" required>
          </div>
          <div class="form-group">
            <label class="form-label">Password *</label>
            <input type="password" name="password" class="form-input" placeholder="Enter password" required>
          </div>
          <div class="form-group">
            <label class="form-label">Phone No. *</label>
            <input type="tel" name="phone" class="form-input" placeholder="Enter phone number" required>
          </div>
          <div class="form-group">
            <label class="form-label">Role *</label>
            <select name="role" class="form-select" required>
              <option value="">Select role</option>
              <option value="Super Admin">Super Admin</option>
              <option value="Admin">Admin</option>
              <option value="Doctor">Doctor</option>
              <option value="Nurse">Nurse</option>
              <option value="Pharmacist">Pharmacist</option>
              <option value="Lab Technician">Lab Technician</option>
              <option value="Accountant">Accountant</option>
              <option value="Receptionist">Receptionist</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Department *</label>
            <select name="department" class="form-select" required>
              <option value="">Select department</option>
              <option value="cardiology">Cardiology</option>
              <option value="neurology">Neurology</option>
              <option value="emergency">Emergency</option>
              <option value="orthopedics">Orthopedics</option>
              <option value="pediatrics">Pediatrics</option>
              <option value="nursing">Nursing</option>
              <option value="pharmacy">Pharmacy</option>
              <option value="laboratory">Laboratory</option>
              <option value="finance">Finance/Accounts</option>
              <option value="operations">Operations</option>
              <option value="hr">Human Resources</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="active" selected>Active</option>
              <option value="inactive">Inactive</option>
              <option value="suspended">Suspended</option>
            </select>
          </div>
          <div class="form-group full-width">
            <label class="form-label">Specialization (Optional)</label>
            <input type="text" name="specialization" class="form-input" placeholder="Enter specialization (optional)">
          </div>
          <div class="form-group full-width">
            <label class="form-label">Schedule (Optional)</label>
            <textarea name="schedule" class="form-textarea" placeholder="Enter work schedule (e.g., Mon-Fri 8:00-16:00)"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeAddStaffModal()">Cancel</button>
        <button type="submit" class="btn-primary">Add Staff Member</button>
      </div>
    </form>
  </div>
</div>

  </div>
</div>

<script>
function openAddStaffModal() {
  document.getElementById('addStaffModal').classList.add('show');
}

function closeAddStaffModal() {
  document.getElementById('addStaffModal').classList.remove('show');
}

// Close modal when clicking outside
document.getElementById('addStaffModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeAddStaffModal();
  }
});

// Search functionality
document.getElementById('searchInput').addEventListener('input', function() {
  const searchTerm = this.value.toLowerCase();
  const staffCards = document.querySelectorAll('.staff-card');
  
  staffCards.forEach(card => {
    const name = card.querySelector('h3').textContent.toLowerCase();
    const role = card.querySelector('.staff-role').textContent.toLowerCase();
    const department = card.querySelector('.detail-item span').textContent.toLowerCase();
    
    if (name.includes(searchTerm) || role.includes(searchTerm) || department.includes(searchTerm)) {
      card.style.display = 'block';
    } else {
      card.style.display = 'none';
    }
  });
});

// Department filter
document.getElementById('departmentFilter').addEventListener('change', function() {
  const selectedDepartment = this.value.toLowerCase().trim();
  const staffCards = document.querySelectorAll('.staff-card');
  
  staffCards.forEach(card => {
    const departmentElement = card.querySelector('.detail-item span');
    const actualDepartment = departmentElement ? departmentElement.textContent.toLowerCase().trim() : '';
    
    if (selectedDepartment === '') {
      card.style.display = 'block';
    } else {
      // Normalize both strings for better matching
      const normalizedSelected = selectedDepartment.replace(/[^a-z0-9]/g, '');
      const normalizedActual = actualDepartment.replace(/[^a-z0-9]/g, '');
      
      if (normalizedActual.includes(normalizedSelected) || normalizedSelected.includes(normalizedActual)) {
        card.style.display = 'block';
      } else {
        card.style.display = 'none';
      }
    }
  });
});

// Form submission
document.getElementById('addStaffForm').addEventListener('submit', function(e) {
  // e.preventDefault();
  alert('Staff member added successfully!');
  closeAddStaffModal();
});
</script>
</body>
</html>
<?php include "includes/footer.php"; ?>