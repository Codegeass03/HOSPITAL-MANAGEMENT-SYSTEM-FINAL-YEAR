<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

// Get services statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_services,
        COUNT(DISTINCT category) as total_categories,
        SUM(price) as total_revenue_potential
    FROM services
");
$stmt->execute();
$stats = $stmt->fetch();

// Get all services
$stmt = $pdo->prepare("SELECT * FROM services ORDER BY category, name");
$stmt->execute();
$services = $stmt->fetchAll();

// Get unique categories
$stmt = $pdo->prepare("SELECT DISTINCT category FROM services ORDER BY category");
$stmt->execute();
$categories = $stmt->fetchAll();
?>

<style>
.services-stats {
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

.service-table-container {
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-top: 1.5rem;
    overflow-x: auto;
}

.service-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
    min-width: 800px;
}

.service-table th {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: #1565c0;
    border-bottom: 1px solid #90caf9;
}

.service-table td {
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.service-table tbody tr:hover {
    background-color: #f8fafc;
}

.service-name {
    font-weight: 600;
    color: #1e293b;
}

.service-description {
    color: #64748b;
    font-size: 0.875rem;
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.service-category {
    padding: 0.25rem 0.75rem;
    background: #e2e8f0;
    color: #475569;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    display: inline-block;
}

.service-price {
    font-weight: 700;
    color: #059669;
    font-family: 'Courier New', monospace;
}

.search-filters {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.search-box {
    position: relative;
    flex: 1;
    min-width: 250px;
}

.search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    z-index: 1;
}

.search-box input {
    width: 100%;
    padding: 0.75rem 0.75rem 0.75rem 2.5rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    background: white;
}

.category-filter, .status-filter {
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    background: white;
    min-width: 150px;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.modal.active {
    display: flex;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
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
    cursor: pointer;
    color: #64748b;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.375rem;
    transition: all 0.2s;
}

.modal-close:hover {
    background-color: #f1f5f9;
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
.form-group {
    margin-bottom: 1.25rem;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #374151;
    font-size: 0.875rem;
}

.form-input, .form-textarea, .form-select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    transition: border-color 0.15s ease;
    background: white;
}

.form-input:focus, .form-textarea:focus, .form-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-textarea {
    resize: vertical;
    min-height: 80px;
}

/* Button Styles */
.btn-primary, .btn-secondary {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

.btn-primary:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
}

.btn-secondary {
    background: #f8fafc;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.btn-secondary:hover {
    background: #f1f5f9;
    color: #374151;
}

.add-product-btn {
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

.add-product-btn:hover {
    background: #2563eb;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
}

/* Status Badges */
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

/* Table Actions */
.table-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.375rem;
    background: white;
    color: #475569;
    text-decoration: none;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}

.action-btn:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    transform: translateY(-1px);
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
@media (max-width: 1024px) {
    .service-table {
        min-width: 700px;
    }
}

@media (max-width: 768px) {
    .services-stats {
        grid-template-columns: 1fr;
    }
    
    .search-filters {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-box {
        min-width: auto;
        max-width: none;
    }
    
    .service-table {
        font-size: 0.75rem;
        min-width: 600px;
    }
    
    .service-table th,
    .service-table td {
        padding: 0.75rem 0.5rem;
    }
    
    .service-description {
        max-width: 200px;
    }
    
    .table-actions {
        flex-direction: column;
    }
    
    .action-btn {
        justify-content: center;
        width: 100%;
    }
    
    .modal-content {
        width: 95%;
        margin: 1rem;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .page-title {
        font-size: 1.5rem;
    }
}

@media (max-width: 640px) {
    .service-table {
        min-width: 500px;
    }
    
    .service-table th:nth-child(3),
    .service-table td:nth-child(3) {
        max-width: 150px;
    }
    
    .modal-body {
        padding: 1rem;
    }
    
    .modal-footer {
        padding: 1rem;
        flex-direction: column;
    }
    
    .btn-primary, .btn-secondary {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .service-table {
        min-width: 400px;
    }
    
    .service-table th:nth-child(2),
    .service-table td:nth-child(2),
    .service-table th:nth-child(5),
    .service-table td:nth-child(5) {
        display: none;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-number {
        font-size: 1.5rem;
    }
}
</style>
<DOCTYPE html>
<head>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
</html>
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
                <i class="fas fa-stethoscope"></i>
                Services Management
            </h1>
            <p class="page-subtitle">
                Manage hospital services and pricing for billing
            </p>
        </div>
        <button class="add-product-btn" onclick="openAddServiceModal()">
            <i class="fas fa-plus"></i>
            Add Service
        </button>
    </div>

    <!-- Services Statistics -->
    <div class="services-stats">
        <div class="stat-card">
            <p class="stat-number"><?php echo $stats['total_services']; ?></p>
            <p class="stat-label">Total Services</p>
        </div>
        
        <div class="stat-card success">
            <p class="stat-number"><?php echo $stats['total_categories']; ?></p>
            <p class="stat-label">Service Categories</p>
        </div>
        
        <div class="stat-card info">
            <p class="stat-number">Ksh <?php echo number_format($stats['total_revenue_potential'] ?: 0, 2); ?></p>
            <p class="stat-label">Total Revenue Potential</p>
        </div>
    </div>

    <div class="search-filters">
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" placeholder="Search services by name or description..." id="searchInput">
        </div>
        <select class="category-filter" id="categoryFilter">
            <option value="">All Categories</option>
            <?php foreach($categories as $category): ?>
            <option value="<?php echo htmlspecialchars($category['category']); ?>">
                <?php echo htmlspecialchars($category['category']); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="service-table-container">
        <table class="service-table">
            <thead>
                <tr>
                    <th>Service Name</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="servicesTableBody">
                <?php foreach($services as $service): ?>
                <tr class="service-row" data-category="<?php echo htmlspecialchars($service['category']); ?>">
                    <td>
                        <div class="service-name"><?php echo htmlspecialchars($service['name']); ?></div>
                    </td>
                    <td>
                        <span class="service-category"><?php echo htmlspecialchars($service['category']); ?></span>
                    </td>
                    <td>
                        <div class="service-description" title="<?php echo htmlspecialchars($service['description'] ?: 'No description'); ?>">
                            <?php echo htmlspecialchars($service['description'] ?: 'No description'); ?>
                        </div>
                    </td>
                    <td>
                        <span class="service-price">Ksh <?php echo number_format($service['price'], 2); ?></span>
                    </td>
                    <td><?php echo $service['duration_minutes'] ? $service['duration_minutes'] . ' mins' : 'N/A'; ?></td>
                    <td>
                        <span class="status-badge <?php echo $service['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $service['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <div class="table-actions">
                            <button class="action-btn action-btn-primary" onclick="editService(<?php echo $service['id']; ?>)">
                                <i class="fas fa-edit"></i>
                                Edit
                            </button>
                            <a href="delete_service.php?id=<?php echo $service['id']; ?>" class="action-btn action-btn-danger" onclick="return confirm('Are you sure you want to delete this service? This action cannot be undone.');">
                                <i class="fas fa-trash"></i>
                                Delete
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Service Modal -->
    <div class="modal" id="addServiceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Add New Service</h2>
                <button class="modal-close" onclick="closeAddServiceModal()">&times;</button>
            </div>
            <form id="addServiceForm" action="process_add_service.php" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Service Name *</label>
                        <input type="text" name="name" class="form-input" placeholder="e.g., Consultation, X-Ray, Blood Test" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <input type="text" name="category" class="form-input" placeholder="e.g., Consultation, Laboratory, Radiology" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" placeholder="Describe the service details"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Price (Ksh) *</label>
                        <input type="number" name="price" class="form-input" placeholder="0.00" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Duration (minutes)</label>
                        <input type="number" name="duration_minutes" class="form-input" placeholder="e.g., 30" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="is_active" class="form-select">
                            <option value="1" selected>Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeAddServiceModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Add Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Modal Functions
function openAddServiceModal() {
    document.getElementById('addServiceModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeAddServiceModal() {
    document.getElementById('addServiceModal').classList.remove('active');
    document.body.style.overflow = 'auto';
}

function editService(serviceId) {
    window.location.href = 'edit_service.php?id=' + serviceId;
}

// Search and filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const serviceRows = document.querySelectorAll('.service-row');
    
    function filterServices() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedCategory = categoryFilter.value;
        
        serviceRows.forEach(row => {
            const serviceName = row.querySelector('.service-name').textContent.toLowerCase();
            const serviceDescription = row.querySelector('.service-description').textContent.toLowerCase();
            const serviceCategory = row.getAttribute('data-category');
            
            const matchesSearch = serviceName.includes(searchTerm) || 
                                serviceDescription.includes(searchTerm);
            const matchesCategory = !selectedCategory || serviceCategory === selectedCategory;
            
            if (matchesSearch && matchesCategory) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    // Event listeners for search and filter
    if (searchInput) {
        searchInput.addEventListener('input', filterServices);
    }
    
    if (categoryFilter) {
        categoryFilter.addEventListener('change', filterServices);
    }
    
    // Close modal when clicking outside
    const modal = document.getElementById('addServiceModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeAddServiceModal();
            }
        });
    }
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAddServiceModal();
        }
    });
    
    // Add form validation
    const addServiceForm = document.getElementById('addServiceForm');
    if (addServiceForm) {
        addServiceForm.addEventListener('submit', function(e) {
            const priceInput = this.querySelector('input[name="price"]');
            const price = parseFloat(priceInput.value);
            
            if (price < 0) {
                e.preventDefault();
                alert('Price cannot be negative');
                priceInput.focus();
                return false;
            }
            
            // Validate required fields
            const nameInput = this.querySelector('input[name="name"]');
            const categoryInput = this.querySelector('input[name="category"]');
            
            if (!nameInput.value.trim()) {
                e.preventDefault();
                alert('Service name is required');
                nameInput.focus();
                return false;
            }
            
            if (!categoryInput.value.trim()) {
                e.preventDefault();
                alert('Category is required');
                categoryInput.focus();
                return false;
            }
            
            return true;
        });
    }
    
    // Enhanced delete confirmation
    const deleteButtons = document.querySelectorAll('.action-btn-danger');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this service? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
});
</script>