<?php
session_start();
include "includes/db_connect.php";

// Handle form submission for adding department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_department') {
    try {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $head_user_id = !empty($_POST['head_user_id']) ? $_POST['head_user_id'] : null;
        
        // Validate required fields
        if (empty($name)) {
            $_SESSION['error'] = "Department name is required.";
        } else {
            // Check if department name already exists
            $checkStmt = $pdo->prepare("SELECT id FROM departments WHERE name = ?");
            $checkStmt->execute([$name]);
            
            if ($checkStmt->fetch()) {
                $_SESSION['error'] = "A department with this name already exists.";
            } else {
                // Begin transaction
                $pdo->beginTransaction();
                
                try {
                    // Insert the new department
                    $stmt = $pdo->prepare("INSERT INTO departments (name, description, head_user_id, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$name, $description, $head_user_id]);
                    
                    // If a head is assigned, update their department
                    if ($head_user_id) {
                        $updateHeadStmt = $pdo->prepare("UPDATE users SET department = ? WHERE id = ?");
                        $updateHeadStmt->execute([$name, $head_user_id]);
                    }
                    
                    $pdo->commit();
                    $_SESSION['success'] = "Department added successfully!";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: Unable to add department. " . $e->getMessage();
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

include "includes/sidebar.php";

// Get department statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_departments,
        COUNT(DISTINCT head_user_id) as departments_with_heads,
        (SELECT COUNT(*) FROM users WHERE department IS NOT NULL) as staff_assigned
    FROM departments
");
$stmt->execute();
$stats = $stmt->fetch();

// Get all departments with head information
$stmt = $pdo->prepare("
    SELECT d.*, u.first_name, u.last_name 
    FROM departments d 
    LEFT JOIN users u ON d.head_user_id = u.id 
    ORDER BY d.name
");
$stmt->execute();
$departments = $stmt->fetchAll();
?>

<style>
/* Add the same styling as your inventory page */
.department-stats {
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
.stat-card.info { border-left-color: #8b5cf6; }

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

.department-table-container {
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-top: 1.5rem;
}

.department-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.department-table th {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: #1565c0;
    border-bottom: 1px solid #90caf9;
}

.department-table td {
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.department-table tbody tr:hover {
    background-color: #f8fafc;
}

.department-head {
    font-weight: 600;
    color: #1e293b;
}

.department-description {
    color: #64748b;
    font-size: 0.875rem;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
}

.status-active { background: #dcfce7; color: #166534; }
.status-inactive { background: #f1f5f9; color: #64748b; }

.table-actions {
    display: flex;
    gap: 0.5rem;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.375rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.375rem;
    background: white;
    color: #475569;
    text-decoration: none;
    font-size: 0.75rem;
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
    color: #ef4444;
    border-color: #fecaca;
}

.action-btn-danger:hover {
    background: #fef2f2;
    color: #dc2626;
    border-color: #fca5a5;
}

/* Alert Styles */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #86efac;
}

.alert-error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
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
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.modal-header {
    display: flex;
    justify-content: space-between;
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
    line-height: 1;
}

.modal-close:hover {
    color: #1e293b;
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

.form-group {
    margin-bottom: 1rem;
}

.form-label {
    display: block;
    font-weight: 500;
    color: #374151;
    margin-bottom: 0.5rem;
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    box-sizing: border-box;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-textarea {
    resize: vertical;
    min-height: 100px;
}

.btn-primary {
    background: #3b82f6;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 0.375rem;
    font-weight: 500;
    cursor: pointer;
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
}

.btn-secondary:hover {
    background: #4b5563;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.page-title {
    font-size: 1.875rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.page-subtitle {
    color: #64748b;
    margin: 0.5rem 0 0 0;
}

.add-product-btn {
    background: #6aa8ffff;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 0.375rem;
    font-weight: 500;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
}

.add-product-btn:hover {
    background: #5897ee;
    transform: translateY(-1px);
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
                <i class="fas fa-sitemap"></i>
                Departments Management
            </h1>
            <p class="page-subtitle">
                Manage hospital departments and assign department heads
            </p>
        </div>
        <button class="add-product-btn" onclick="openAddDepartmentModal()">
            <i class="fas fa-plus"></i>
            Add Department
        </button>
    </div>
<br>
    <!-- Department Statistics -->
    <div class="department-stats">
        <div class="stat-card">
            <p class="stat-number"><?php echo $stats['total_departments']; ?></p>
            <p class="stat-label">Total Departments</p>
        </div>
        
        <div class="stat-card success">
            <p class="stat-number"><?php echo $stats['departments_with_heads']; ?></p>
            <p class="stat-label">With Department Heads</p>
        </div>
        
        <div class="stat-card info">
            <p class="stat-number"><?php echo $stats['staff_assigned']; ?></p>
            <p class="stat-label">Staff Assigned</p>
        </div>
    </div>

    <div class="department-table-container">
        <table class="department-table">
            <thead>
                <tr>
                    <th>Department Name</th>
                    <th>Department Head</th>
                    <th>Description</th>
                    <th>Staff Count</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($departments)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 2rem; color: #64748b;">
                        No departments found. Add your first department to get started.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach($departments as $department): 
                    // Get staff count for this department
                    $stmt = $pdo->prepare("SELECT COUNT(*) as staff_count FROM users WHERE department = ?");
                    $stmt->execute([$department['name']]);
                    $staff_count = $stmt->fetch()['staff_count'];
                ?>
                <tr>
                    <td>
                        <div class="department-head"><?php echo htmlspecialchars($department['name']); ?></div>
                    </td>
                    <td>
                        <?php if ($department['first_name']): ?>
                            <?php echo htmlspecialchars($department['first_name'] . ' ' . $department['last_name']); ?>
                        <?php else: ?>
                            <span style="color: #9ca3af;">Not assigned</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="department-description">
                            <?php echo htmlspecialchars($department['description'] ?: 'No description'); ?>
                        </div>
                    </td>
                    <td><?php echo $staff_count; ?> staff</td>
                    <td>
                        <span class="status-badge status-active">Active</span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <button class="action-btn action-btn-primary" onclick="editDepartment(<?php echo $department['id']; ?>)">
                                <i class="fas fa-edit"></i>
                                Edit
                            </button>
                            <a href="delete_department.php?id=<?php echo $department['id']; ?>" class="action-btn action-btn-danger" onclick="return confirm('Are you sure you want to delete this department?');">
                                <i class="fas fa-trash"></i>
                                Delete
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Department Modal -->
    <div class="modal" id="addDepartmentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Department</h2>
                <button class="modal-close" onclick="closeAddDepartmentModal()">&times;</button>
            </div>
            <form id="addDepartmentForm" method="POST">
                <input type="hidden" name="action" value="add_department">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Department Name *</label>
                        <input type="text" name="name" class="form-input" placeholder="e.g., Cardiology, Pediatrics" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" placeholder="Describe the department's functions and responsibilities"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Department Head</label>
                        <select name="head_user_id" class="form-select">
                            <option value="">Select Department Head</option>
                            <?php
                            $stmt = $pdo->prepare("SELECT id, first_name, last_name, role FROM users WHERE role IN ('Doctor', 'Head') ORDER BY first_name, last_name");
                            $stmt->execute();
                            $users = $stmt->fetchAll();
                            foreach($users as $user): 
                            ?>
                            <option value="<?php echo $user['id']; ?>">
                                Dr. <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?> - <?php echo $user['role']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeAddDepartmentModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Add Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddDepartmentModal() {
    document.getElementById('addDepartmentModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeAddDepartmentModal() {
    document.getElementById('addDepartmentModal').classList.remove('show');
    document.body.style.overflow = 'auto';
}

function editDepartment(departmentId) {
    window.location.href = 'edit_department.php?id=' + departmentId;
}

// Close modal when clicking outside
document.getElementById('addDepartmentModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAddDepartmentModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddDepartmentModal();
    }
});

// Form validation
document.getElementById('addDepartmentForm').addEventListener('submit', function(e) {
    const departmentName = this.querySelector('input[name="name"]').value.trim();
    
    if (!departmentName) {
        e.preventDefault();
        alert('Department name is required.');
        return;
    }
});
</script>

<?php include "includes/footer.php"; ?>