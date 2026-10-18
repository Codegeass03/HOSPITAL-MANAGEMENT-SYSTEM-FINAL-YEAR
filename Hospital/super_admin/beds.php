<?php
session_start();  
include "includes/db_connect.php";
include "includes/sidebar.php";

$ward_id = $_GET['ward_id'] ?? null;
$ward = null;

if ($ward_id) {
    $stmt = $pdo->prepare("SELECT * FROM ward WHERE id = ?");
    $stmt->execute([$ward_id]);
    $ward = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
  
</body>
</html>

<style>
.add-ward-btn{
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 0.375rem;
  background: #3b82f6;
  color: white;
  font-size: 18px;
  font-weight: bold;
  cursor: pointer;
  transition: background-color 0.2s;
  margin-bottom: 12px;
}

.bed-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1rem;
}

.bed-card {
  background: white;
  border-radius: 0.75rem;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.2s;
  border: 1px solid #f1f5f9;
  position: relative;
}

.bed-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.bed-card.occupied {
  border-left: 4px solid #ef4444;
}

.bed-card.available {
  border-left: 4px solid #10b981;
}

.bed-card.maintenance {
  border-left: 4px solid #f59e0b;
}

.bed-card.reserved {
  border-left: 4px solid #f59e0b;
}

.bed-card.cleaning {
  border-left: 4px solid #3b82f6;
}

.bed-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.bed-number {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
  margin: 0;
}

.bed-type {
  padding: 0.25rem 0.5rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
}

.bed-type.Standard { background: #e2e8f0; color: #475569; }
.bed-type.ICU { background: #fee2e2; color: #dc2626; }
.bed-type.Ventilator { background: #fef3c7; color: #92400e; }
.bed-type.Oxygen { background: #dbeafe; color: #1d4ed8; }
.bed-type.Isolation { background: #f3e8ff; color: #7c3aed; }
.bed-type.Electric { background: #ecfdf5; color: #047857; }
.bed-type.Manual { background: #fffbeb; color: #b45309; }

.bed-details {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.detail-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.detail-icon {
  color: #64748b;
  width: 16px;
}

.bed-price {
  font-weight: 600;
  color: #059669;
}

.bed-features {
  font-size: 0.75rem;
  color: #64748b;
}

.bed-status {
  position: absolute;
  top: 1rem;
  right: 1rem;
  padding: 0.25rem 0.5rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  font-weight: 500;
  text-transform: uppercase;
}

.status-available { background: #dcfce7; color: #166534; }
.status-occupied { background: #fee2e2; color: #dc2626; }
.status-reserved { background: #fef3c7; color: #92400e; }
.status-maintenance { background: #e2e8f0; color: #475569; }
.status-cleaning { background: #dbeafe; color: #1d4ed8; }

.bed-actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 1rem;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.375rem;
  background: white;
  color: #475569;
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
}

.action-btn:hover {
  background: #f8fafc;
  border-color: #cbd5e1;
}

.action-btn-primary {
  background: #3b82f6;
  color: white;
  border-color: #3b82f6;
}

.action-btn-primary:hover {
  background: #2563eb;
  border-color: #2563eb;
}

.action-btn-danger {
  color: #ef4444 !important;
  border-color: #fecaca !important;
}

.action-btn-danger:hover {
  background: #fef2f2 !important;
  color: #dc2626 !important;
  border-color: #fca5a5 !important;
}

/* Modal Styles */
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
}

.modal.show {
  display: flex;
}

.modal-content {
  background: white;
  border-radius: 0.75rem;
  width: 90%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.modal-header {
  display: flex;
  justify-content: between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
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
  padding: 0;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.modal-close:hover {
  color: #475569;
}

.modal-body {
  padding: 1.5rem;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
  padding: 1.5rem;
  border-top: 1px solid #e2e8f0;
}

/* Form Styles */
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
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

.form-input, .form-select, .form-textarea {
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  transition: border-color 0.2s;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
}

/* Button Styles */
.btn-primary {
  background: #3b82f6;
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 0.375rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s;
}

.btn-primary:hover {
  background: #2563eb;
}

.btn-secondary {
  background: #6b7280;
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 0.375rem;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s;
  text-decoration: none;
}

.btn-secondary:hover {
  background: #4b5563;
}

/* Statistics Cards */
.ward-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  padding: 1.5rem;
  border-radius: 0.75rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  text-align: center;
  border-left: 4px solid #3b82f6;
}

.stat-card.success { border-left-color: #10b981; }
.stat-card.warning { border-left-color: #f59e0b; }
.stat-card.danger { border-left-color: #ef4444; }

.stat-number {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0 0 0.5rem 0;
}

.stat-label {
  color: #64748b;
  font-size: 0.875rem;
  font-weight: 500;
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* Search and Filters */
.search-filters {
  display: flex;
  gap: 1rem;
  margin-bottom: 1.5rem;
  align-items: center;
}

.search-box {
  position: relative;
  flex: 1;
  max-width: 400px;
}

.search-icon {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: #64748b;
}

.search-box input {
  width: 100%;
  padding: 0.75rem 0.75rem 0.75rem 2.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  font-size: 0.875rem;
}

.search-box input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.type-filter {
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  background: white;
  min-width: 150px;
}

.type-filter:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
  <div>
 <a href="wards.php?ward_id=<?php echo $ward_id; ?>" class="btn-secondary" >
                    <i class="fas fa-arrow-left"></i>
                    Back to Wards
                </a>
                </div>
  <div class="page-header">
    <div>
      <h1 class="page-title">
        <i class="fas fa-bed"></i>
        Bed Management
        <?php if ($ward): ?>
        - <?php echo htmlspecialchars($ward['ward_name']); ?>
        <?php endif; ?>
      </h1>
      <p class="page-subtitle">
        <?php if ($ward): ?>
        Manage beds in <?php echo htmlspecialchars($ward['ward_name']); ?> (Floor <?php echo $ward['floor_number']; ?>)
        <?php else: ?>
        Select a ward to manage beds
        <?php endif; ?>
      </p>
    </div>
    
    <?php if ($ward): ?>
    <button class="add-ward-btn" onclick="openAddBedModal()">
      <i class="fas fa-plus"></i>
      Add Bed
    </button>
    <?php endif; ?>
  </div>

  <?php if (!$ward): ?>
  <div class="alert alert-error">
    <i class="fas fa-exclamation-triangle"></i>
    Please select a ward from the Ward Management page to view beds.
  </div>
  <?php else: ?>
  
  <!-- Bed Statistics -->
  <div class="ward-stats">
    <?php
    // Get bed statistics for this ward
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM beds WHERE ward_id = ?");
    $stmt->execute([$ward_id]);
    $total_beds = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM beds WHERE ward_id = ? AND bed_status = 'occupied'");
    $stmt->execute([$ward_id]);
    $occupied_beds = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM beds WHERE ward_id = ? AND bed_status = 'available'");
    $stmt->execute([$ward_id]);
    $available_beds = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM beds WHERE ward_id = ? AND bed_status = 'maintenance'");
    $stmt->execute([$ward_id]);
    $maintenance_beds = $stmt->fetchColumn();
    ?>
    
    <div class="stat-card">
      <p class="stat-number"><?php echo $total_beds ?: 0; ?></p>
      <p class="stat-label">Total Beds</p>
    </div>
    
    <div class="stat-card success">
      <p class="stat-number"><?php echo $available_beds ?: 0; ?></p>
      <p class="stat-label">Available</p>
    </div>
    
    <div class="stat-card warning">
      <p class="stat-number"><?php echo $occupied_beds ?: 0; ?></p>
      <p class="stat-label">Occupied</p>
    </div>
    
    <div class="stat-card danger">
      <p class="stat-number"><?php echo $maintenance_beds ?: 0; ?></p>
      <p class="stat-label">Maintenance</p>
    </div>
  </div>

  <div class="search-filters">
    <div class="search-box">
      <i class="fas fa-search search-icon"></i>
      <input type="text" placeholder="Search beds by number or type..." id="searchInput">
    </div>
    <select class="type-filter" id="statusFilter">
      <option value="">All Status</option>
      <option value="available">Available</option>
      <option value="occupied">Occupied</option>
      <option value="reserved">Reserved</option>
      <option value="maintenance">Maintenance</option>
      <option value="cleaning">Cleaning</option>
    </select>
  </div>

  <div class="bed-grid">
    <?php
    $stmt = $pdo->prepare("SELECT * FROM beds WHERE ward_id = ? ORDER BY bed_number");
    $stmt->execute([$ward_id]);
    $beds = $stmt->fetchAll();
    
    if (count($beds) > 0):
      foreach($beds as $bed):
    ?>
    <div class="bed-card <?php echo $bed['bed_status']; ?>">
      <div class="bed-header">
        <h3 class="bed-number"><?php echo htmlspecialchars($bed['bed_number']); ?></h3>
        <span class="bed-type <?php echo str_replace(' ', '', $bed['bed_type']); ?>">
          <?php echo $bed['bed_type']; ?>
        </span>
      </div>
      
      <span class="bed-status status-<?php echo $bed['bed_status']; ?>">
        <?php echo ucfirst($bed['bed_status']); ?>
      </span>
      
      <div class="bed-details">
        <div class="detail-item">
          <i class="fas fa-money-bill detail-icon"></i>
          <span class="bed-price">Ksh <?php echo number_format($bed['price_per_day'], 2); ?>/day</span>
        </div>
        
        <?php if ($bed['features']): ?>
        <div class="detail-item">
          <i class="fas fa-star detail-icon"></i>
          <span class="bed-features"><?php echo htmlspecialchars($bed['features']); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if ($bed['description']): ?>
        <div class="detail-item">
          <i class="fas fa-info-circle detail-icon"></i>
          <span><?php echo htmlspecialchars($bed['description']); ?></span>
        </div>
        <?php endif; ?>
      </div>
      
      <div class="bed-actions">
        <?php if ($bed['bed_status'] == 'available'): ?>
        <a href="assign_bed.php?bed_id=<?php echo $bed['id']; ?>" class="action-btn action-btn-primary">
          <i class="fas fa-user-plus"></i>
          Assign
        </a>
        <?php elseif ($bed['bed_status'] == 'occupied'): ?>
        <a href="discharge_bed.php?bed_id=<?php echo $bed['id']; ?>" class="action-btn action-btn-danger">
          <i class="fas fa-user-minus"></i>
          Discharge
        </a>
        <?php endif; ?>
        
        <a href="edit_bed.php?id=<?php echo $bed['id']; ?>&ward_id=<?php echo $ward_id; ?>" class="action-btn">
          <i class="fas fa-edit"></i>
          Edit
        </a>
        
        <a href="delete_bed.php?id=<?php echo $bed['id']; ?>&ward_id=<?php echo $ward_id; ?>" 
           class="action-btn action-btn-danger"
           onclick="return confirm('Are you sure you want to delete this bed?');">
          <i class="fas fa-trash"></i>
          Delete
        </a>
      </div>
    </div>
    <?php 
      endforeach;
    else: 
    ?>
    <div class="alert alert-error" style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
      <i class="fas fa-bed" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
      <h3 style="margin: 0 0 0.5rem 0; color: #374151;">No beds found</h3>
      <p style="margin: 0; color: #64748b;">No beds found in this ward. Click "Add Bed" to create the first bed.</p>
    </div>
    <?php endif; ?>
  </div>

  <!-- Add Bed Modal -->
  <div class="modal" id="addBedModal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title">Add New Bed</h2>
        <button class="modal-close" onclick="closeAddBedModal()">&times;</button>
      </div>
      <form id="addBedForm" action="process_add_bed.php" method="POST">
        <input type="hidden" name="ward_id" value="<?php echo $ward_id; ?>">
        <div class="modal-body">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Bed Number *</label>
              <input type="text" name="bed_number" class="form-input" placeholder="e.g., BED-001" required>
            </div>
            <div class="form-group">
              <label class="form-label">Bed Type *</label>
              <select name="bed_type" class="form-select" required>
                <option value="">Select type</option>
                <option value="Standard">Standard</option>
                <option value="ICU">ICU</option>
                <option value="Ventilator">Ventilator</option>
                <option value="Oxygen Support">Oxygen Support</option>
                <option value="Isolation">Isolation</option>
                <option value="Electric">Electric</option>
                <option value="Manual">Manual</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Price per Day *</label>
              <input type="number" name="price_per_day" class="form-input" placeholder="e.g., 5000" step="0.01" min="0" required>
            </div>
            <div class="form-group">
              <label class="form-label">Status</label>
              <select name="bed_status" class="form-select">
                <option value="available" selected>Available</option>
                <option value="occupied">Occupied</option>
                <option value="reserved">Reserved</option>
                <option value="maintenance">Maintenance</option>
                <option value="cleaning">Cleaning</option>
              </select>
            </div>
            <div class="form-group full-width">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-textarea" placeholder="Enter bed description"></textarea>
            </div>
            <div class="form-group full-width">
              <label class="form-label">Features</label>
              <textarea name="features" class="form-textarea" placeholder="Enter features (comma separated)"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-secondary" onclick="closeAddBedModal()">Cancel</button>
          <button type="submit" class="btn-primary">Add Bed</button>
        </div>
      </form>
    </div>
  </div>

  <?php endif; ?>
</div>

<script>
function openAddBedModal() {
  document.getElementById('addBedModal').classList.add('show');
}

function closeAddBedModal() {
  document.getElementById('addBedModal').classList.remove('show');
}

// Search functionality
document.getElementById('searchInput').addEventListener('input', function() {
  const searchTerm = this.value.toLowerCase();
  const bedCards = document.querySelectorAll('.bed-card');
  
  bedCards.forEach(card => {
    const number = card.querySelector('.bed-number').textContent.toLowerCase();
    const type = card.querySelector('.bed-type').textContent.toLowerCase();
    
    if (number.includes(searchTerm) || type.includes(searchTerm)) {
      card.style.display = 'block';
    } else {
      card.style.display = 'none';
    }
  });
});

// Status filter
document.getElementById('statusFilter').addEventListener('change', function() {
  const selectedStatus = this.value;
  const bedCards = document.querySelectorAll('.bed-card');
  
  bedCards.forEach(card => {
    if (selectedStatus === '') {
      card.style.display = 'block';
    } else {
      const hasStatus = card.classList.contains(selectedStatus);
      card.style.display = hasStatus ? 'block' : 'none';
    }
  });
});

// Close modal when clicking outside
document.getElementById('addBedModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeAddBedModal();
  }
});
</script>

<?php include "includes/footer.php"; ?>