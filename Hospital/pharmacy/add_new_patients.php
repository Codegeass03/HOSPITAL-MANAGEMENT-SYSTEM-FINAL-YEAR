<?php
session_start();  
// if(!isset($_SESSION["super_admin"])) {
//     header("Location: ../index.php");
//     exit();
// }

include "includes/db_connect.php";
include "includes/sidebar.php";
?>

<!-- Add this CSS to your style section -->
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
  <title>Patient Management</title>
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

    /* Your existing main-content style */
    .main-content {
      padding: 2rem;
      flex: 1;
      background-color: #f8fafc;
      min-height: 100vh;
    }

    .page-header {
      display: flex;
      justify-content: space-between; /* Fixed: Changed from "between" to "space-between" for proper right alignment */
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
  list-style: none;        /* removes bullets */
  padding: 0;
  margin: 0;
}

.sidebar li {
  margin-bottom: 0.5rem;   /* adds space between items */
}

.sidebar a {
  display: block;
  text-decoration: none;   /* removes underline */
  color: white;            /* text color */
  padding: 0.15rem;   /* adds spacing around text */
  border-radius: 0.375rem; /* rounded corners on hover */
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
      justify-content: space-between; /* Fixed: Changed from "between" to "space-between" for proper alignment */
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
      justify-content: space-between; /* Fixed: Changed from "between" to "space-between" for proper alignment */
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
// Add this right after your opening <div class="main-content"> tag

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
        Patient Management
      </h1>
      <p class="page-subtitle">Manage Patient records and Information!</p>
    </div>
 <!-- <button class="add-staff-btn" onclick="window.location.href='patients.php'">
  <i class="fas fa-plus"></i>
  Add New Patient
</button> -->
    
  </div>

  <div class="search-filters">
    <div class="search-box">
      <i class="fas fa-search search-icon"></i>
      <input type="text" placeholder="Search patient by name, patientID, doctor or condition..." id="searchInput">
    </div>
    <!-- <select class="department-filter" id="departmentFilter">
      <option value="">All Departments</option>
      <option value="cardiology">Cardiology</option>
      <option value="neurology">Neurology</option>
      <option value="emergency">Emergency</option>
      <option value="orthopedics">Orthopedics</option>
      <option value="pediatrics">Pediatrics</option>
      <option value="pharmacy">Pharmacy</option>
      <option value="lab">Lab</option>
      <option value="accounts">Finance</option>
      <option value="operations">Operations</option>
      <option value="hr">HR</option>
    </select> -->
  </div>

<div class="staff-grid">
  <?php
  // Fetch actual patients from database - REPLACE THE STAFF QUERY WITH THIS
  try {
      $stmt = $pdo->query("SELECT * FROM patients ORDER BY created_at DESC");
      $patients = $stmt->fetchAll();
      
      if (empty($patients)) {
          echo '<div class="no-patients" style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #64748b;">
                  <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                  <h3>No Patients Found</h3>
                  <p>Get started by adding your first patient to the system.</p>
                </div>';
      }
      
      foreach($patients as $patient):
          $initials = strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1));
          $age = $patient['age']; // Now using the actual age from database
          $last_visit = $patient['last_visit'] ?? 'No visits yet';
  ?>
    <div class="staff-card">
      <div class="staff-header">
        <div class="staff-avatar"><?php echo $initials; ?></div>
        <span class="status-badge status-<?php echo strtolower($patient['status'] ?? 'active'); ?>">
          <?php echo ucfirst($patient['status'] ?? 'Active'); ?>
        </span>
      </div>
      <div class="staff-info">
        <h3><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h3>
        <div class="staff-role">Patient ID: <?php echo htmlspecialchars($patient['patient_id']); ?></div>
      </div>
      <div class="staff-details">
        <div class="detail-item">
          <i class="fas fa-user detail-icon"></i>
          <span>Age: <?php echo $age; ?> - <?php echo htmlspecialchars($patient['gender']); ?></span>
        </div>
        <div class="detail-item">
          <i class="fas fa-phone detail-icon"></i>
          <span><?php echo htmlspecialchars($patient['phone']); ?></span>
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
        <?php if (!empty($patient['last_visit'])): ?>
        <div class="detail-item">
          <i class="fas fa-calendar detail-icon"></i>
          <span>Last Visit: <?php echo date('Y-m-d', strtotime($patient['last_visit'])); ?></span>
        </div>
        <?php endif; ?>
      </div>
      <div class="staff-actions">
        <!-- <a href="edit_patient.php?id=<?php echo $patient['id']; ?>" class="action-btn" title="Edit Patient">
          <i class="fas fa-edit"></i>
        </a>
        <a href="tel:<?php echo $patient['phone']; ?>" class="action-btn" title="Call">
          <i class="fas fa-phone"></i>
        </a>
        <?php if (!empty($patient['email'])): ?>
        <a href="mailto:<?php echo $patient['email']; ?>" class="action-btn" title="Email">
          <i class="fas fa-envelope"></i>
        </a>
        <?php endif; ?>
        <!-- <a href="delete_patient.php?id=<?php echo $patient['id']; ?>" 
           class="action-btn action-btn-danger" 
           title="Delete Patient"
           onclick="return confirm('Are you sure you want to delete this patient? This action cannot be undone.');">
          <i class="fas fa-trash"></i>
        </a> -->
      </div> 
    </div>
  <?php 
      endforeach;
  } catch (PDOException $e) {
      echo '<div class="alert alert-error">Error loading patients: ' . htmlspecialchars($e->getMessage()) . '</div>';
  }
  ?>
</div>

<!-- Add this CSS to your style section -->
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
</div>

<!-- Add patients Modal -->
<div class="modal" id="addStaffModal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 class="modal-title">Add New Patient</h2>
      <button class="modal-close" onclick="closeAddStaffModal()">&times;</button>
    </div>
    <form id="addStaffForm" action="process_add_patient.php" method="POST">
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
            <label class="form-label">Date of Birth *</label>
            <input type="date" name="date_of_birth" class="form-input" required>
          </div>
          <div class="form-group">
            <label class="form-label">Age *</label>
            <input type="number" name="age" class="form-input" placeholder="Enter age" min="0" max="150" required>
          </div>
          <div class="form-group">
            <label class="form-label">Gender *</label>
            <select name="gender" class="form-select" required>
              <option value="">Select gender</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Phone No. *</label>
            <input type="tel" name="phone" class="form-input" placeholder="Enter phone number" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-input" placeholder="Enter email (optional)">
          </div>
          <div class="form-group">
            <label class="form-label">Height (cm)</label>
            <input type="number" name="height" class="form-input" placeholder="Enter height in cm" step="0.1" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Weight (kg)</label>
            <input type="number" name="weight" class="form-input" placeholder="Enter weight in kg" step="0.1" min="0">
          </div>
          <div class="form-group">
            <label class="form-label">Blood Type</label>
            <select name="blood_type" class="form-select">
              <option value="">Select blood type</option>
              <option value="A+">A+</option>
              <option value="A-">A-</option>
              <option value="B+">B+</option>
              <option value="B-">B-</option>
              <option value="AB+">AB+</option>
              <option value="AB-">AB-</option>
              <option value="O+">O+</option>
              <option value="O-">O-</option>
            </select>
          </div>
          <div class="form-group full-width">
            <label class="form-label">Address</label>
            <input type="text" name="address" class="form-input" placeholder="Enter full home address (optional)">
          </div>
          <div class="form-group">
            <label class="form-label">Emergency Contact Name</label>
            <input type="text" name="emergency_contact_name" class="form-input" placeholder="Emergency contact name">
          </div>
          <div class="form-group">
            <label class="form-label">Emergency Contact Phone</label>
            <input type="tel" name="emergency_contact_phone" class="form-input" placeholder="Emergency contact phone">
          </div>
          <div class="form-group">
            <label class="form-label">Insurance Provider</label>
            <input type="text" name="insurance_provider" class="form-input" placeholder="Insurance provider">
          </div>
          <div class="form-group">
            <label class="form-label">Insurance ID</label>
            <input type="text" name="insurance_id" class="form-input" placeholder="Insurance ID">
          </div>
          <div class="form-group full-width">
            <label class="form-label">Medical History</label>
            <textarea name="medical_history" class="form-textarea" placeholder="Previous medical conditions, surgeries, etc." rows="3"></textarea>
          </div>
          <div class="form-group full-width">
            <label class="form-label">Allergies</label>
            <textarea name="allergies" class="form-textarea" placeholder="Known allergies" rows="2"></textarea>
          </div>
          <div class="form-group full-width">
            <label class="form-label">Current Medications</label>
            <textarea name="current_medications" class="form-textarea" placeholder="Current medications and dosages" rows="2"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeAddStaffModal()">Cancel</button>
        <button type="submit" class="btn-primary">Add Patient</button>
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
// Search functionality for patients
document.getElementById('searchInput').addEventListener('input', function() {
  const searchTerm = this.value.toLowerCase();
  const patientCards = document.querySelectorAll('.staff-card');
  
  patientCards.forEach(card => {
    const name = card.querySelector('h3').textContent.toLowerCase();
    const patientId = card.querySelector('.staff-role').textContent.toLowerCase();
    const phoneElement = card.querySelector('.detail-item:nth-child(2) span');
    const emailElement = card.querySelector('.detail-item:nth-child(3) span');
    
    const phone = phoneElement ? phoneElement.textContent.toLowerCase() : '';
    const email = emailElement ? emailElement.textContent.toLowerCase() : '';
    
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

// Form submission
document.getElementById('addStaffForm').addEventListener('submit', function(e) {
  // e.preventDefault();
  // Add your form submission logic here
  alert('Patient added successfully!');
  closeAddStaffModal();
});
</script>
</body>
</html>
<?php include "includes/footer.php"; ?>