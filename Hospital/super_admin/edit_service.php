<?php
session_start();
include "includes/db_connect.php";

$service_id = $_GET['id'] ?? null;

if (!$service_id) {
    $_SESSION['error'] = 'No service ID specified.';
    header('Location: services.php');
    exit;
}

// Fetch service details
$stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if (!$service) {
    $_SESSION['error'] = 'Service not found.';
    header('Location: services.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name']);
        $category = trim($_POST['category']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $duration_minutes = !empty($_POST['duration_minutes']) ? intval($_POST['duration_minutes']) : null;
        $is_active = intval($_POST['is_active']);

        // Validate required fields
        $errors = [];
        if (empty($name)) {
            $errors[] = "Service name is required.";
        }
        if (empty($category)) {
            $errors[] = "Category is required.";
        }
        if ($price < 0) {
            $errors[] = "Price cannot be negative.";
        }

        // Check if service name already exists (excluding current service)
        $check_stmt = $pdo->prepare("SELECT id FROM services WHERE name = ? AND id != ?");
        $check_stmt->execute([$name, $service_id]);
        if ($check_stmt->fetch()) {
            $errors[] = "A service with this name already exists.";
        }

        if (!empty($errors)) {
            $error_message = implode(" ", $errors);
        } else {
            $stmt = $pdo->prepare("
                UPDATE services 
                SET name = ?, category = ?, description = ?, price = ?, duration_minutes = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$name, $category, $description, $price, $duration_minutes, $is_active, $service_id]);
            
            $_SESSION['success'] = 'Service updated successfully!';
            header('Location: services.php');
            exit;
        }
        
    } catch (Exception $e) {
        $error_message = 'Error updating service: ' . $e->getMessage();
    }
}

// Include sidebar after header redirects
include "includes/sidebar.php";
?>

<style>
.edit-service-container {
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    padding: 2rem;
    margin-top: 1.5rem;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
}

.service-info-card {
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
    width: 100%;
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
    transition: all 0.2s;
}

.btn-danger:hover {
    background: #b91c1c;
    transform: translateY(-1px);
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
    font-size: 0.875rem;
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    transition: border-color 0.15s ease;
    background: white;
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
    transition: all 0.2s;
    text-decoration: none;
}

.btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
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
    transition: all 0.2s;
}

.btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-1px);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    display: inline-block;
}

.status-active {
    background: #dcfce7;
    color: #166534;
}

.status-inactive {
    background: #fef2f2;
    color: #991b1b;
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

/* Responsive Design */
@media (max-width: 768px) {
    .edit-service-container {
        padding: 1.5rem;
        margin: 1rem;
    }
    
    .service-info-card {
        padding: 1rem;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn-primary, .btn-secondary, .btn-danger {
        width: 100%;
        justify-content: center;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .page-title {
        font-size: 1.5rem;
    }
}

@media (max-width: 480px) {
    .edit-service-container {
        padding: 1rem;
        margin: 0.5rem;
    }
    
    .service-info-card {
        padding: 0.75rem;
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
                <i class="fas fa-edit"></i>
                Edit Service
            </h1>
            <p class="page-subtitle">
                Update service information and pricing
            </p>
        </div>
        <a href="services.php" class="btn-secondary">
            <i class="fas fa-arrow-left"></i>
            Back to Services
        </a>
    </div>

    <!-- Service Information Card -->
    <div class="service-info-card">
        <h3 style="margin: 0 0 0.5rem 0; color: #1e293b;">Service Information</h3>
        <p style="margin: 0; color: #64748b; font-size: 0.875rem;">Current service details and status</p>
        
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Current Name</span>
                <span class="info-value"><?php echo htmlspecialchars($service['name']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Category</span>
                <span class="info-value"><?php echo htmlspecialchars($service['category']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Current Price</span>
                <span class="info-value">Ksh <?php echo number_format($service['price'], 2); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Status</span>
                <span class="status-badge <?php echo $service['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                    <?php echo $service['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Duration</span>
                <span class="info-value">
                    <?php echo $service['duration_minutes'] ? $service['duration_minutes'] . ' minutes' : 'Not specified'; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Last Updated</span>
                <span class="info-value">
                    <?php echo $service['updated_at'] ? date('M j, Y g:i A', strtotime($service['updated_at'])) : 'Never'; ?>
                </span>
            </div>
        </div>
    </div>

    <div class="edit-service-container">
        <form method="POST" class="service-form" id="editServiceForm">
            <div class="form-group">
                <label class="form-label">Service Name *</label>
                <input type="text" name="name" class="form-input" 
                       value="<?php echo htmlspecialchars($service['name']); ?>" 
                       placeholder="e.g., Consultation, X-Ray, Blood Test" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Category *</label>
                <input type="text" name="category" class="form-input" 
                       value="<?php echo htmlspecialchars($service['category']); ?>" 
                       placeholder="e.g., Consultation, Laboratory, Radiology" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-textarea" 
                          placeholder="Describe the service details and any important information"><?php echo htmlspecialchars($service['description']); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Price (Ksh) *</label>
                <input type="number" name="price" class="form-input" 
                       value="<?php echo htmlspecialchars($service['price']); ?>" 
                       step="0.01" min="0" placeholder="0.00" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Duration (minutes)</label>
                <input type="number" name="duration_minutes" class="form-input" 
                       value="<?php echo htmlspecialchars($service['duration_minutes']); ?>" 
                       min="0" placeholder="e.g., 30">
                <small style="color: #64748b; font-size: 0.75rem; margin-top: 0.25rem; display: block;">
                    Optional: Estimated duration of the service in minutes
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="is_active" class="form-select">
                    <option value="1" <?php echo $service['is_active'] ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo !$service['is_active'] ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i>
                    Update Service
                </button>
                
                <a href="services.php" class="btn-secondary">
                    <i class="fas fa-times"></i>
                    Cancel
                </a>
                
                <a href="delete_service.php?id=<?php echo $service_id; ?>" class="btn-danger" 
                   onclick="return confirm('Are you sure you want to delete this service? This action cannot be undone.')">
                    <i class="fas fa-trash"></i>
                    Delete Service
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('editServiceForm');
    
    form.addEventListener('submit', function(e) {
        const nameInput = this.querySelector('input[name="name"]');
        const categoryInput = this.querySelector('input[name="category"]');
        const priceInput = this.querySelector('input[name="price"]');
        
        // Validate required fields
        if (!nameInput.value.trim()) {
            e.preventDefault();
            alert('Service name is required.');
            nameInput.focus();
            return;
        }
        
        if (!categoryInput.value.trim()) {
            e.preventDefault();
            alert('Category is required.');
            categoryInput.focus();
            return;
        }
        
        const price = parseFloat(priceInput.value);
        if (price < 0) {
            e.preventDefault();
            alert('Price cannot be negative.');
            priceInput.focus();
            return;
        }
        
        // Confirm if making significant changes
        const originalName = '<?php echo addslashes($service['name']); ?>';
        const originalPrice = <?php echo $service['price']; ?>;
        const originalStatus = <?php echo $service['is_active']; ?>;
        
        const newName = nameInput.value.trim();
        const newPrice = parseFloat(priceInput.value);
        const newStatus = parseInt(this.querySelector('select[name="is_active"]').value);
        
        let changes = [];
        if (newName !== originalName) changes.push('name');
        if (newPrice !== originalPrice) changes.push('price');
        if (newStatus !== originalStatus) changes.push('status');
        
        if (changes.length > 0) {
            const confirmation = confirm(`You are about to update the following: ${changes.join(', ')}. Continue?`);
            if (!confirmation) {
                e.preventDefault();
                return;
            }
        }
    });
    
    // Real-time validation
    const inputs = form.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            if (this.hasAttribute('required') && !this.value.trim()) {
                this.style.borderColor = '#ef4444';
            } else {
                this.style.borderColor = '#d1d5db';
            }
        });
        
        // Validate on blur
        input.addEventListener('blur', function() {
            if (this.name === 'price' && this.value) {
                const price = parseFloat(this.value);
                if (price < 0) {
                    this.style.borderColor = '#ef4444';
                } else {
                    this.style.borderColor = '#d1d5db';
                }
            }
        });
    });
});
</script>