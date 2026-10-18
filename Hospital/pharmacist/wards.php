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

.ward-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  border-radius: 0.75rem;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  border-left: 4px solid #0ea5e9;
}

.stat-card.warning {
  border-left-color: #f59e0b;
}

.stat-card.danger {
  border-left-color: #ef4444;
}

.stat-card.success {
  border-left-color: #10b981;
}

.stat-number {
  font-size: 2rem;
  font-weight: 700;
  color: #1e293b;
  margin: 0;
}

.stat-label {
  color: #64748b;
  font-size: 0.875rem;
  margin: 0.25rem 0 0 0;
}

.ward-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1.5rem;
}

.ward-card {
  background: white;
  border-radius: 0.75rem;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.2s;
  border: 1px solid #f1f5f9;
}

.ward-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.ward-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.ward-icon {
  width: 50px;
  height: 50px;
  border-radius: 0.5rem;
  background: linear-gradient(135deg, #0ea5e9, #0284c7);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 1.25rem;
}

.ward-info h3 {
  margin: 0 0 0.25rem 0;
  color: #1e293b;
  font-size: 1.125rem;
  font-weight: 600;
}

.ward-type {
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

.status-maintenance {
  background-color: #fef3c7;
  color: #92400e;
}

.status-closed {
  background-color: #fee2e2;
  color: #dc2626;
}

.ward-details {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
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

.bed-progress {
  margin: 1rem 0;
}

.progress-label {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.5rem;
  font-size: 0.875rem;
  color: #64748b;
}

.progress-bar {
  height: 8px;
  background: #e2e8f0;
  border-radius: 4px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: linear-gradient(135deg, #0ea5e9, #0284c7);
  border-radius: 4px;
  transition: width 0.3s ease;
}

.ward-actions {
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

.action-btn-primary {
  background: #0ea5e9;
  color: white;
  border-color: #0ea5e9;
}

.action-btn-primary:hover {
  background: #0284c7;
  color: white;
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
  max-width: 600px;
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

.add-ward-btn {
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

.add-ward-btn:hover {
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

.type-filter {
  padding: 0.75rem 1rem;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  background: white;
  font-size: 0.875rem;
  color: #475569;
  min-width: 150px;
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
        <i class="fas fa-hospital"></i>
        Ward Management
      </h1>
      <p class="page-subtitle">Manage hospital wards and bed allocations</p>
    </div>
 
   <button class="add-ward-btn" onclick="openAddWardModal()">
  <i class="fas fa-plus"></i>
  Add New Ward
</button>
  </div>

  <!-- Ward Statistics -->
  <div class="ward-stats">
    <?php
    // Get ward statistics
    $total_wards = $pdo->query("SELECT COUNT(*) FROM ward WHERE status != 'closed'")->fetchColumn();
    $total_beds = $pdo->query("SELECT COUNT(*) FROM beds")->fetchColumn();
    $occupied_beds = $pdo->query("SELECT COUNT(*) FROM beds WHERE bed_status = 'occupied'")->fetchColumn();
    $available_beds = $total_beds - $occupied_beds;
    $occupancy_rate = $total_beds > 0 ? round(($occupied_beds / $total_beds) * 100, 1) : 0;
    ?>
    
    <div class="stat-card">
      <p class="stat-number"><?php echo $total_wards; ?></p>
      <p class="stat-label">Total Wards</p>
    </div>
    
    <div class="stat-card">
      <p class="stat-number"><?php echo $total_beds; ?></p>
      <p class="stat-label">Total Beds</p>
    </div>
    
    <div class="stat-card <?php echo $occupied_beds > ($total_beds * 0.8) ? 'warning' : 'success'; ?>">
      <p class="stat-number"><?php echo $occupied_beds; ?></p>
      <p class="stat-label">Occupied Beds</p>
    </div>
    
    <div class="stat-card <?php echo $available_beds < ($total_beds * 0.1) ? 'danger' : 'success'; ?>">
      <p class="stat-number"><?php echo $available_beds; ?></p>
      <p class="stat-label">Available Beds</p>
    </div>
    
    <div class="stat-card <?php echo $occupancy_rate > 80 ? 'warning' : ($occupancy_rate > 90 ? 'danger' : 'success'); ?>">
      <p class="stat-number"><?php echo $occupancy_rate; ?>%</p>
      <p class="stat-label">Occupancy Rate</p>
    </div>
  </div>

  <div class="search-filters">
    <div class="search-box">
      <i class="fas fa-search search-icon"></i>
      <input type="text" placeholder="Search wards by name or department..." id="searchInput">
    </div>
    <select class="type-filter" id="typeFilter">
      <option value="">All Types</option>
      <option value="General">General</option>
      <option value="ICU">ICU</option>
      <option value="NICU">NICU</option>
      <option value="Pediatric">Pediatric</option>
      <option value="Maternity">Maternity</option>
      <option value="Surgical">Surgical</option>
      <option value="Emergency">Emergency</option>
      <option value="Private">Private</option>
      <option value="Semi-Private">Semi-Private</option>
    </select>
  </div>

  <div class="ward-grid">
  <?php
  // Fetch wards with bed counts
  $stmt = $pdo->query("
    SELECT w.*, 
           COUNT(b.id) as total_beds,
           SUM(CASE WHEN b.bed_status = 'occupied' THEN 1 ELSE 0 END) as occupied_beds
    FROM ward w 
    LEFT JOIN beds b ON w.id = b.ward_id 
    GROUP BY w.id 
    ORDER BY w.ward_name
  ");
  $wards = $stmt->fetchAll();
  
  foreach($wards as $ward):
    $available_beds = $ward['total_beds'] - $ward['occupied_beds'];
    $occupancy_rate = $ward['total_beds'] > 0 ? ($ward['occupied_beds'] / $ward['total_beds']) * 100 : 0;
  ?>
    <div class="ward-card">
      <div class="ward-header">
        <div class="ward-icon">
          <i class="fas fa-procedures"></i>
        </div>
        <span class="status-badge status-<?php echo $ward['status']; ?>">
          <?php echo ucfirst($ward['status']); ?>
        </span>
      </div>
      <div class="ward-info">
        <h3><?php echo htmlspecialchars($ward['ward_name']); ?></h3>
        <div class="ward-type"><?php echo htmlspecialchars($ward['ward_type']); ?> • Floor <?php echo $ward['floor_number']; ?></div>
      </div>
      <div class="ward-details">
        <div class="detail-item">
          <i class="fas fa-hashtag detail-icon"></i>
          <span>Ward Number: <?php echo htmlspecialchars($ward['ward_number']); ?></span>
        </div>
        <div class="detail-item">
          <i class="fas fa-building detail-icon"></i>
          <span>Department: <?php echo htmlspecialchars($ward['department'] ?? 'General'); ?></span>
        </div>
        <div class="detail-item">
          <i class="fas fa-bed detail-icon"></i>
          <span>Capacity: <?php echo $ward['capacity']; ?> beds</span>
        </div>
      </div>
      
      <div class="bed-progress">
        <div class="progress-label">
          <span>Bed Occupancy</span>
          <span><?php echo $ward['occupied_beds']; ?>/<?php echo $ward['total_beds']; ?> beds</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width: <?php echo $occupancy_rate; ?>%"></div>
        </div>
      </div>
      
      <div class="ward-actions">
        <a href="beds.php?ward_id=<?php echo $ward['id']; ?>" class="action-btn action-btn-primary" title="Manage Beds">
          <i class="fas fa-bed"></i>
          Beds
        </a>
        <a href="javascript:void(0)" class="action-btn" title="Edit Ward" onclick="editWard(<?php echo $ward['id']; ?>)">
          <i class="fas fa-edit"></i>
        </a>
        <a href="delete_ward.php?id=<?php echo $ward['id']; ?>" 
           class="action-btn action-btn-danger" 
           title="Delete Ward"
           onclick="return confirm('Are you sure you want to delete this ward? This will also delete all associated beds.');">
          <i class="fas fa-trash"></i>
        </a>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Add Ward Modal -->
<div class="modal" id="addWardModal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 class="modal-title">Add New Ward</h2>
      <button class="modal-close" onclick="closeAddWardModal()">&times;</button>
    </div>
    <form id="addWardForm" action="process_add_ward.php" method="POST">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Ward Number *</label>
            <input type="text" name="ward_number" class="form-input" placeholder="e.g., WARD-001" required>
          </div>
          <div class="form-group">
            <label class="form-label">Ward Name *</label>
            <input type="text" name="ward_name" class="form-input" placeholder="e.g., Cardiology Ward" required>
          </div>
          <div class="form-group">
            <label class="form-label">Ward Type *</label>
            <select name="ward_type" class="form-select" required>
              <option value="">Select type</option>
              <option value="General">General</option>
              <option value="ICU">ICU</option>
              <option value="NICU">NICU</option>
              <option value="Pediatric">Pediatric</option>
              <option value="Maternity">Maternity</option>
              <option value="Surgical">Surgical</option>
              <option value="Emergency">Emergency</option>
              <option value="Private">Private</option>
              <option value="Semi-Private">Semi-Private</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Floor Number *</label>
            <input type="number" name="floor_number" class="form-input" placeholder="e.g., 3" min="1" max="20" required>
          </div>
          <div class="form-group">
            <label class="form-label">Department</label>
            <input type="text" name="department" class="form-input" placeholder="e.g., Cardiology">
          </div>
          <div class="form-group">
            <label class="form-label">Capacity *</label>
            <input type="number" name="capacity" class="form-input" placeholder="e.g., 20" min="1" max="100" required>
          </div>
          <div class="form-group full-width">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-textarea" placeholder="Enter ward description"></textarea>
          </div>
          <div class="form-group full-width">
            <label class="form-label">Facilities</label>
            <textarea name="facilities" class="form-textarea" placeholder="Enter facilities (comma separated)"></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="active" selected>Active</option>
              <option value="inactive">Inactive</option>
              <option value="maintenance">Maintenance</option>
              <option value="closed">Closed</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="closeAddWardModal()">Cancel</button>
        <button type="submit" class="btn-primary">Add Ward</button>
      </div>
    </form>
  </div>
</div>

  </div>
</div>

<script>
function openAddWardModal() {
  document.getElementById('addWardModal').classList.add('show');
}

function closeAddWardModal() {
  document.getElementById('addWardModal').classList.remove('show');
}

function editWard(wardId) {
  // Implement edit functionality
  alert('Edit ward with ID: ' + wardId);
  // You can open another modal or redirect to edit page
}

// Close modal when clicking outside
document.getElementById('addWardModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeAddWardModal();
  }
});

// Search functionality
document.getElementById('searchInput').addEventListener('input', function() {
  const searchTerm = this.value.toLowerCase();
  const wardCards = document.querySelectorAll('.ward-card');
  
  wardCards.forEach(card => {
    const name = card.querySelector('h3').textContent.toLowerCase();
    const type = card.querySelector('.ward-type').textContent.toLowerCase();
    const department = card.querySelector('.detail-item:nth-child(2) span').textContent.toLowerCase();
    
    if (name.includes(searchTerm) || type.includes(searchTerm) || department.includes(searchTerm)) {
      card.style.display = 'block';
    } else {
      card.style.display = 'none';
    }
  });
});

// Type filter
document.getElementById('typeFilter').addEventListener('change', function() {
  const selectedType = this.value.toLowerCase();
  const wardCards = document.querySelectorAll('.ward-card');
  
  wardCards.forEach(card => {
    if (selectedType === '') {
      card.style.display = 'block';
    } else {
      const type = card.querySelector('.ward-type').textContent.toLowerCase();
      if (type.includes(selectedType)) {
        card.style.display = 'block';
      } else {
        card.style.display = 'none';
      }
    }
  });
});
</script>
</body>
</html>
<?php include "includes/footer.php"; ?>