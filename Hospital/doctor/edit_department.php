<?php
session_start();
include "includes/db_connect.php";

$department_id = $_GET['id'] ?? null;

if (!$department_id) {
    $_SESSION['error'] = 'No department ID specified.';
    header('Location: departments.php');
    exit;
}

// Fetch department details
$stmt = $pdo->prepare("
    SELECT d.*, u.first_name, u.last_name 
    FROM departments d 
    LEFT JOIN users u ON d.head_user_id = u.id 
    WHERE d.id = ?
");
$stmt->execute([$department_id]);
$department = $stmt->fetch();

if (!$department) {
    $_SESSION['error'] = 'Department not found.';
    header('Location: departments.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $head_user_id = !empty($_POST['head_user_id']) ? intval($_POST['head_user_id']) : null;
        $old_department_name = $department['name'];
        $old_head_user_id = $department['head_user_id'];

        // Validation checks
        $errors = [];

        // Required field validation
        if (empty($name)) {
            $errors[] = "Department name is required.";
        }

        // Check if department name already exists (excluding current department)
        $check_stmt = $pdo->prepare("SELECT id FROM departments WHERE name = ? AND id != ?");
        $check_stmt->execute([$name, $department_id]);
        if ($check_stmt->fetch()) {
            $errors[] = "A department with this name already exists.";
        }

        // Validate head user exists if provided
        if ($head_user_id) {
            $user_check = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $user_check->execute([$head_user_id]);
            if (!$user_check->fetch()) {
                $errors[] = "Selected department head does not exist.";
            }
        }

        // If there are errors, store them and don't proceed
        if (!empty($errors)) {
            $error_message = implode(" ", $errors);
        } else {
            // Begin transaction for data consistency
            $pdo->beginTransaction();

            try {
                // Update the department
                $update_stmt = $pdo->prepare("
                    UPDATE departments 
                    SET name = ?, description = ?, head_user_id = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                $update_stmt->execute([$name, $description, $head_user_id, $department_id]);

                // If department name changed, update all users in this department
                if ($name !== $old_department_name) {
                    $update_users_stmt = $pdo->prepare("UPDATE users SET department = ? WHERE department = ?");
                    $update_users_stmt->execute([$name, $old_department_name]);
                }

                // Update the new head user's department
                if ($head_user_id) {
                    $update_head_stmt = $pdo->prepare("UPDATE users SET department = ? WHERE id = ?");
                    $update_head_stmt->execute([$name, $head_user_id]);
                }

                // Remove department from previous head if head changed
                if ($old_head_user_id && $old_head_user_id != $head_user_id) {
                    // Only remove if the old head is not assigned to this department through other means
                    $check_old_head = $pdo->prepare("SELECT department FROM users WHERE id = ?");
                    $check_old_head->execute([$old_head_user_id]);
                    $old_head_dept = $check_old_head->fetch();
                    
                    if ($old_head_dept && $old_head_dept['department'] === $name) {
                        $remove_old_head_stmt = $pdo->prepare("UPDATE users SET department = NULL WHERE id = ?");
                        $remove_old_head_stmt->execute([$old_head_user_id]);
                    }
                }

                $pdo->commit();

                $_SESSION['success'] = 'Department updated successfully!';
                header('Location: departments.php');
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    } catch (PDOException $e) {
        error_log("Database error in edit_department.php: " . $e->getMessage());
        $error_message = 'Error updating department: Database error. Please try again.';
    } catch (Exception $e) {
        error_log("Error in edit_department.php: " . $e->getMessage());
        $error_message = 'Error updating department: ' . $e->getMessage();
    }
}

include "includes/sidebar.php";

// Get all users for department head selection
$users_stmt = $pdo->prepare("SELECT id, first_name, last_name, role, department FROM users WHERE role IN ('Doctor', 'Head', 'Admin') ORDER BY first_name, last_name");
$users_stmt->execute();
$users = $users_stmt->fetchAll();

// Get staff count for this department
$staff_stmt = $pdo->prepare("SELECT COUNT(*) as staff_count FROM users WHERE department = ?");
$staff_stmt->execute([$department['name']]);
$staff_count = $staff_stmt->fetch()['staff_count'];
?>

<style>
.edit-department-container {
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 2rem;
    margin-top: 1.5rem;
}

.department-info-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.25rem;
}

.info-value {
    font-weight: 500;
    color: #1e293b;
}

.service-form {
    max-width: 600px;
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-start;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e2e8f0;
    flex-wrap: wrap;
}

.btn-danger {
    background: #dc2626;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 0.375rem;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-danger:hover {
    background: #b91c1c;
}

.alert {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
}

.alert-success {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.alert-error {
    background: #fef2f2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.form-group {
    margin-bottom: 1.5rem;
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
    transition: border-color 0.15s ease;
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
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
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
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
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
</style>

<div class="main-content">
    <?php
    $success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
    $error_message_session = isset($_SESSION['error']) ? $_SESSION['error'] : null;
    unset($_SESSION['success'], $_SESSION['error']);
    
    // Use the error from form submission if it exists, otherwise use session error
    $display_error = isset($error_message) ? $error_message : $error_message_session;
    ?>

    <?php if ($success_message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
    <?php endif; ?>

    <?php if ($display_error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo htmlspecialchars($display_error); ?>
    </div>
    <?php endif; ?>

    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Edit Department
            </h1>
            <p class="page-subtitle">
                Update department information and assign department head
            </p>
        </div>
        <a href="departments.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Back to Departments
        </a>
    </div>

    <!-- Department Information Card -->
    <div class="department-info-card">
        <h3 style="margin: 0 0 0.5rem 0; color: #1e293b;">Department Information</h3>
        <p style="margin: 0; color: #64748b; font-size: 0.875rem;">Current department details and statistics</p>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Current Name</span>
                <span class="info-value"><?php echo htmlspecialchars($department['name']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Staff Members</span>
                <span class="info-value"><?php echo $staff_count; ?> staff</span>
            </div>
            <div class="info-item">
                <span class="info-label">Current Head</span>
                <span class="info-value">
                    <?php if ($department['first_name']): ?>
                        Dr. <?php echo htmlspecialchars($department['first_name'] . ' ' . $department['last_name']); ?>
                    <?php else: ?>
                        <span style="color: #9ca3af;">Not assigned</span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Created</span>
                <span class="info-value">
                    <?php echo $department['created_at'] ? date('M j, Y', strtotime($department['created_at'])) : 'N/A'; ?>
                </span>
            </div>
        </div>
    </div>

    <div class="edit-department-container">
        <form method="POST" class="service-form" id="editDepartmentForm">
            <div class="form-group">
                <label class="form-label">Department Name *</label>
                <input type="text" name="name" class="form-input" 
                       value="<?php echo htmlspecialchars($department['name']); ?>" 
                       placeholder="e.g., Cardiology, Pediatrics" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-textarea" 
                          placeholder="Describe the department's functions and responsibilities"><?php echo htmlspecialchars($department['description']); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Department Head</label>
                <select name="head_user_id" class="form-select">
                    <option value="">Select Department Head</option>
                    <?php foreach($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>" 
                        <?php echo $user['id'] == $department['head_user_id'] ? 'selected' : ''; ?>>
                        Dr. <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?> 
                        - <?php echo $user['role']; ?>
                        <?php echo $user['department'] ? ' (' . $user['department'] . ')' : ''; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: #64748b; font-size: 0.75rem; margin-top: 0.25rem; display: block;">
                    Note: Assigning a department head will automatically set their department to this one.
                </small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i>
                    Update Department
                </button>
                
                <a href="departments.php" class="btn-secondary">
                    <i class="fas fa-times"></i>
                    Cancel
                </a>
                
                <a href="delete_department.php?id=<?php echo $department_id; ?>" class="btn-danger" 
                   onclick="return confirm('Are you sure you want to delete this department? This will remove all department assignments from staff members.')">
                    <i class="fas fa-trash"></i>
                    Delete Department
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editDepartmentForm');
    
    form.addEventListener('submit', function(e) {
        const departmentName = this.querySelector('input[name="name"]').value.trim();
        
        if (!departmentName) {
            e.preventDefault();
            alert('Department name is required.');
            return;
        }
        
        if (departmentName.length < 2) {
            e.preventDefault();
            alert('Department name must be at least 2 characters long.');
            return;
        }
        
        // Confirm if changing department name (as it affects all staff)
        const originalName = '<?php echo addslashes($department['name']); ?>';
        if (departmentName !== originalName) {
            const confirmation = confirm('Changing the department name will update the department assignment for all ' + <?php echo $staff_count; ?> + ' staff members. Are you sure you want to continue?');
            if (!confirmation) {
                e.preventDefault();
                return;
            }
        }
        
        // Confirm if changing department head
        const originalHead = '<?php echo $department['head_user_id'] ?? ''; ?>';
        const newHead = this.querySelector('select[name="head_user_id"]').value;
        if (newHead !== originalHead && newHead !== '') {
            const headConfirmation = confirm('Changing the department head will update their department assignment. Continue?');
            if (!headConfirmation) {
                e.preventDefault();
                return;
            }
        }
    });
    
    // Add real-time validation
    const nameInput = document.querySelector('input[name="name"]');
    if (nameInput) {
        nameInput.addEventListener('input', function() {
            if (this.value.trim().length < 2 && this.value.trim().length > 0) {
                this.style.borderColor = '#ef4444';
            } else {
                this.style.borderColor = '#d1d5db';
            }
        });
    }
});
</script>

<?php include "includes/footer.php"; ?>