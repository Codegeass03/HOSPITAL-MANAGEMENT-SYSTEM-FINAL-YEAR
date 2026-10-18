<?php
include "admin/connection.php";
include "includes/db_connect.php";
include "includes/sidebar.php";

// Get inventory statistics
$total_products = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as total FROM stock_master"))['total'];
$low_stock = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as total FROM stock_master WHERE product_quantity <= 10"))['total'];
$out_of_stock = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as total FROM stock_master WHERE product_quantity = 0"))['total'];
$total_value = mysqli_fetch_assoc(mysqli_query($link, "SELECT SUM(product_quantity * product_selling_price) as total FROM stock_master"))['total'];
?>
<DOCTYPE html>
<head>
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
</html>
<style>
/* Statistics Cards */
.inventory-stats {
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

/* Search and Filters */
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

.type-filter, .status-filter {
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    background: white;
    min-width: 150px;
}

.type-filter:focus, .status-filter:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Inventory Grid */
.inventory-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.inventory-card {
    background: white;
    border-radius: 0.75rem;
    padding: 1.5rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #f1f5f9;
    transition: all 0.2s;
    position: relative;
}

.inventory-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.inventory-card.low-stock {
    border-left: 4px solid #f59e0b;
}

.inventory-card.out-of-stock {
    border-left: 4px solid #ef4444;
}

.inventory-card.in-stock {
    border-left: 4px solid #10b981;
}

.inventory-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.product-name {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
    flex: 1;
}

.product-company {
    padding: 0.25rem 0.5rem;
    background: #e2e8f0;
    color: #475569;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
}

.inventory-details {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f1f5f9;
}

.detail-label {
    font-size: 0.875rem;
    color: #64748b;
    font-weight: 500;
}

.detail-value {
    font-weight: 600;
    color: #1e293b;
}

.quantity-badge {
    position: absolute;
    top: 1rem;
    right: 1rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
}

.quantity-in-stock { background: #dcfce7; color: #166534; }
.quantity-low-stock { background: #fef3c7; color: #92400e; }
.quantity-out-of-stock { background: #fee2e2; color: #dc2626; }

.inventory-actions {
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
    flex: 1;
    justify-content: center;
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

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.page-title {
    font-size: 1.875rem;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-subtitle {
    color: #64748b;
    margin-top: 0.5rem;
}

.add-product-btn {
    background: #3b82f6;
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
    transition: background-color 0.2s;
}

.add-product-btn:hover {
    background: #2563eb;
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

/* Empty State */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3rem;
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.empty-state i {
    font-size: 3rem;
    color: #9ca3af;
    margin-bottom: 1rem;
}

.empty-state h3 {
    color: #374151;
    margin: 0 0 0.5rem 0;
}

.empty-state p {
    color: #6b7280;
    margin: 0;
}

/* Table Styles */
.inventory-table-container {
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-top: 1.5rem;
}

.inventory-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.inventory-table th {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: #1565c0;
    border-bottom: 1px solid #90caf9;
    font-size: 0.875rem;
}

.inventory-table td {
    padding: 1rem;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.inventory-table tbody tr {
    transition: background-color 0.2s;
}

.inventory-table tbody tr:hover {
    background-color: #f8fafc;
}

.inventory-table tbody tr:last-child td {
    border-bottom: none;
}

/* Status Badges */
.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.status-in-stock {
    background: #dcfce7;
    color: #166534;
}

.status-low-stock {
    background: #fef3c7;
    color: #92400e;
}

.status-out-of-stock {
    background: #fee2e2;
    color: #dc2626;
}

/* Table Actions */
.actions-cell {
    white-space: nowrap;
}

.table-actions {
    display: flex;
    gap: 0.5rem;
}

.table-actions .action-btn {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    text-decoration: none;
}

/* Quantity Cell */
.quantity-cell {
    font-weight: 600;
    font-family: 'Courier New', monospace;
}

/* Product Name */
.product-name {
    font-weight: 600;
    color: #1e293b;
}

/* Empty Table State */
.empty-table-state {
    padding: 0 !important;
}

.empty-table-state .empty-state {
    padding: 3rem;
    text-align: center;
    background: white;
}

.empty-table-state .empty-state i {
    font-size: 3rem;
    color: #9ca3af;
    margin-bottom: 1rem;
}

.empty-table-state .empty-state h3 {
    color: #374151;
    margin: 0 0 0.5rem 0;
}

.empty-table-state .empty-state p {
    color: #6b7280;
    margin: 0;
}
</style>

<div class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-boxes"></i>
                Inventory Management
            </h1>
            <p class="page-subtitle">
                Manage the hospital's inventory and stock levels
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="add_new_unit.php" class="add-product-btn" style="background: #6b7280;">
                <i class="fas fa-plus"></i>
                Add Unit Of Measurement
            </a>
            <a href="add_products.php" class="add-product-btn" style="background: #10b981;">
                <i class="fas fa-plus"></i>
                Add Product
            </a>
            <a href="purchase.php" class="add-product-btn" style="background: #f59e0b;">
                <i class="fas fa-shopping-cart"></i>
                Purchase
            </a>
        </div>
    </div>

    <!-- Inventory Statistics -->
    <div class="inventory-stats">
        <div class="stat-card">
            <p class="stat-number"><?php echo $total_products; ?></p>
            <p class="stat-label">Total Products</p>
        </div>
        
        <div class="stat-card success">
            <p class="stat-number"><?php echo $total_products - $low_stock - $out_of_stock; ?></p>
            <p class="stat-label">In Stock</p>
        </div>
        
        <div class="stat-card warning">
            <p class="stat-number"><?php echo $low_stock; ?></p>
            <p class="stat-label">Low Stock</p>
        </div>
        
        <div class="stat-card danger">
            <p class="stat-number"><?php echo $out_of_stock; ?></p>
            <p class="stat-label">Out of Stock</p>
        </div>
        
        <div class="stat-card info">
            <p class="stat-number">Ksh <?php echo number_format($total_value ?: 0, 2); ?></p>
            <p class="stat-label">Total Value</p>
        </div>
    </div>

    <div class="search-filters">
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" placeholder="Search products by name or company..." id="searchInput">
        </div>
        <select class="type-filter" id="companyFilter">
            <option value="">All Companies</option>
            <?php
            $companies = mysqli_query($link, "SELECT DISTINCT product_company FROM stock_master ORDER BY product_company");
            while($company = mysqli_fetch_assoc($companies)) {
                echo '<option value="' . htmlspecialchars($company['product_company']) . '">' . htmlspecialchars($company['product_company']) . '</option>';
            }
            ?>
        </select>
        <select class="status-filter" id="stockFilter">
            <option value="">All Stock Status</option>
            <option value="in-stock">In Stock</option>
            <option value="low-stock">Low Stock</option>
            <option value="out-of-stock">Out of Stock</option>
        </select>
    </div>

        <div class="inventory-table-container">
        <table class="inventory-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Company Name</th>
                    <th>Product Name</th>
                    <th>Unit</th>
                    <th>Packing Size</th>
                    <th>Quantity</th>
                    <th>Selling Price</th>
                    <th>Total Value</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $res = mysqli_query($link, "select * from stock_master ORDER BY product_name");
                $hasProducts = false;
                $count = 0;
                
                while($row = mysqli_fetch_array($res)) {
                    $hasProducts = true;
                    $count++;
                    $quantity = $row["product_quantity"];
                    $stockStatus = 'in-stock';
                    $statusClass = 'status-in-stock';
                    $statusText = 'In Stock';
                    
                    if ($quantity == 0) {
                        $stockStatus = 'out-of-stock';
                        $statusClass = 'status-out-of-stock';
                        $statusText = 'Out of Stock';
                    } elseif ($quantity <= 10) {
                        $stockStatus = 'low-stock';
                        $statusClass = 'status-low-stock';
                        $statusText = 'Low Stock';
                    }
                    
                    $totalValue = $quantity * $row["product_selling_price"];
                ?>
                <tr class="inventory-row" data-company="<?php echo htmlspecialchars($row["product_company"]); ?>" data-stock="<?php echo $stockStatus; ?>">
                    <td><?php echo $count; ?></td>
                    <td><?php echo htmlspecialchars($row["product_company"]); ?></td>
                    <td class="product-name"><?php echo htmlspecialchars($row["product_name"]); ?></td>
                    <td><?php echo htmlspecialchars($row["product_unit"]); ?></td>
                    <td><?php echo htmlspecialchars($row["packing_size"]); ?></td>
                    <td class="quantity-cell"><?php echo $quantity; ?></td>
                    <td>Ksh <?php echo number_format($row["product_selling_price"], 2); ?></td>
                    <td>Ksh <?php echo number_format($totalValue, 2); ?></td>
                    <td>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <?php echo $statusText; ?>
                        </span>
                    </td>
                    <td class="actions-cell">
                        <div class="table-actions">
                            <a href="edit_stock_master.php?id=<?php echo $row["id"]; ?>" class="action-btn action-btn-primary">
                                <i class="fas fa-edit"></i>
                                Edit
                            </a>
                            <a href="delete_products.php?id=<?php echo $row["id"]; ?>" class="action-btn action-btn-danger" onclick="return confirm('Are you sure you want to delete this product?');">
                                <i class="fas fa-trash"></i>
                                Delete
                            </a>
                        </div>
                    </td>
                </tr>
                <?php } ?>
                
                <?php if (!$hasProducts): ?>
                <tr>
                    <td colspan="10" class="empty-table-state">
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <h3>No Products Found</h3>
                            <p>Get started by adding your first product to the inventory.</p>
                            <a href="add_products.php" class="add-product-btn" style="margin-top: 1rem;">
                                <i class="fas fa-plus"></i>
                                Add First Product
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Search functionality
document.getElementById('searchInput').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const productRows = document.querySelectorAll('.inventory-row');
    
    productRows.forEach(row => {
        const productName = row.querySelector('.product-name').textContent.toLowerCase();
        const companyName = row.cells[1].textContent.toLowerCase(); // Company is in second cell (index 1)
        
        if (productName.includes(searchTerm) || companyName.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Company filter
document.getElementById('companyFilter').addEventListener('change', function() {
    filterProducts();
});

// Stock status filter
document.getElementById('stockFilter').addEventListener('change', function() {
    filterProducts();
});

function filterProducts() {
    const selectedCompany = document.getElementById('companyFilter').value;
    const selectedStock = document.getElementById('stockFilter').value;
    const productRows = document.querySelectorAll('.inventory-row');
    
    productRows.forEach(row => {
        const company = row.getAttribute('data-company');
        const stock = row.getAttribute('data-stock');
        
        const companyMatch = !selectedCompany || company === selectedCompany;
        const stockMatch = !selectedStock || stock === selectedStock;
        
        if (companyMatch && stockMatch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Initialize filters
filterProducts();
</script>

<?php
include "includes/footer.php";
?>