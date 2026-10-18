<?php
session_start();

if (!isset($_SESSION["user"]) || $_SESSION['usertype'] != 'a') {
    header("location: ../login.php");
    exit();
}

include("../connection.php");

function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

$message = "";
// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add') {
            $company_name = sanitize_input($_POST['company_name']);
            $product_category = sanitize_input($_POST['product_category']);
            $product_name = sanitize_input($_POST['product_name']);
            $unit = sanitize_input($_POST['unit']);
            $packing_size = sanitize_input($_POST['packing_size']);
            
            // Check if product already exists
            $check = $database->query("SELECT * FROM products WHERE product_name='$product_name' AND company_name='$company_name'");
            if ($check->num_rows > 0) {
                $message = "<p style='color: red;'>This product already exists in the catalog!</p>";
            } else {
                $sql = "INSERT INTO products (company_name, product_category, product_name, unit, packing_size) 
                        VALUES ('$company_name', '$product_category', '$product_name', '$unit', '$packing_size')";
                
                if ($database->query($sql)) {
                    $message = "<p style='color: green;'>Product added to catalog successfully!</p>";
                } else {
                    $message = "<p style='color: red;'>Error: " . $database->error . "</p>";
                }
            }
        }
        
        if ($action == 'delete') {
            $id = sanitize_input($_POST['id']);
            
            $sql = "DELETE FROM products WHERE id='$id'";
            
            if ($database->query($sql)) {
                $message = "<p style='color: green;'>Product removed from catalog!</p>";
            } else {
                $message = "<p style='color: red;'>Error: " . $database->error . "</p>";
            }
        }
        
        if ($action == 'edit') {
            $id = sanitize_input($_POST['id']);
            $unit = sanitize_input($_POST['unit']);
            $packing_size = sanitize_input($_POST['packing_size']);
            
            $sql = "UPDATE products SET unit='$unit', packing_size='$packing_size' WHERE id='$id'";
            
            if ($database->query($sql)) {
                $message = "<p style='color: green;'>Product updated successfully!</p>";
            } else {
                $message = "<p style='color: red;'>Error: " . $database->error . "</p>";
            }
        }
    }
}

// Filter and search
$category_filter = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

$where_clause = "";
if ($category_filter) {
    $where_clause = "WHERE product_category = '$category_filter'";
}

if ($search) {
    $search_condition = " (product_name LIKE '%$search%' OR company_name LIKE '%$search%')";
    $where_clause = $where_clause ? "$where_clause AND $search_condition" : "WHERE $search_condition";
}

// Get products
$products = $database->query("SELECT * FROM products $where_clause ORDER BY product_name");

// Get companies
$companies = $database->query("SELECT DISTINCT company_name FROM company_name ORDER BY company_name");

// Get units
$units = $database->query("SELECT DISTINCT unit FROM unit ORDER BY unit");

// Get product statistics
$total_products = $database->query("SELECT COUNT(*) as count FROM products")->fetch_assoc()['count'];
$medications = $database->query("SELECT COUNT(*) as count FROM products WHERE product_category = 'Medications'")->fetch_assoc()['count'];
$equipment = $database->query("SELECT COUNT(*) as count FROM products WHERE product_category = 'Medical Equipment'")->fetch_assoc()['count'];
$supplies = $database->query("SELECT COUNT(*) as count FROM products WHERE product_category = 'Medical Supplies'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">  
    <link rel="stylesheet" href="../css/main.css">  
    <title>Product Catalog - MediAccess</title>
    <style>
        .menu-container {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .menu-container a {
            margin: 0 10px;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
        }
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card h2 {
            color: #3498db;
            margin: 10px 0;
        }
        .filter-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #3498db;
            color: white;
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        .btn-info {
            background: #1abc9c;
            color: white;
        }
        input, select {
            padding: 10px;
            border: 1px solid #bdc3c7;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }
        th {
            background-color: #3498db;
            color: white;
        }
        tr:hover {
            background-color: #ecf0f1;
        }
        .category-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        .cat-medications {
            background: #3498db;
        }
        .cat-equipment {
            background: #e74c3c;
        }
        .cat-supplies {
            background: #27ae60;
        }
        .cat-office {
            background: #f39c12;
        }
        .cat-laboratory {
            background: #9b59b6;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            width: 80%;
            max-width: 600px;
            border-radius: 10px;
        }
        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="menu-container">
            <a href="pharmacy.php">← Back to Pharmacy</a>
            <a href="pharmacy_inventory.php">Inventory</a>
            <a href="pharmacy_suppliers.php">Suppliers</a>
            <a href="pharmacy_purchase.php">Purchase</a>
            <a href="pharmacy_dispense.php">Dispense</a>
            <a href="pharmacy_products.php">Products</a>
        </div>

        <h1>Product Catalog</h1>
        <p style="color: #7f8c8d;">Manage your product catalog for easy ordering and inventory management</p>
        <?php echo $message; ?>

        <div class="stats-container">
            <div class="stat-card">
                <p>Total Products</p>
                <h2><?php echo $total_products; ?></h2>
            </div>
            <div class="stat-card">
                <p>Medications</p>
                <h2><?php echo $medications; ?></h2>
            </div>
            <div class="stat-card">
                <p>Medical Equipment</p>
                <h2><?php echo $equipment; ?></h2>
            </div>
            <div class="stat-card">
                <p>Medical Supplies</p>
                <h2><?php echo $supplies; ?></h2>
            </div>
        </div>

        <div class="filter-container">
            <a href="pharmacy_products.php" class="btn <?php echo !$category_filter ? 'btn-primary' : 'btn-info'; ?>">All</a>
            <a href="pharmacy_products.php?category=Medications" class="btn <?php echo $category_filter == 'Medications' ? 'btn-primary' : 'btn-info'; ?>">Medications</a>
            <a href="pharmacy_products.php?category=Medical Equipment" class="btn <?php echo $category_filter == 'Medical Equipment' ? 'btn-primary' : 'btn-info'; ?>">Equipment</a>
            <a href="pharmacy_products.php?category=Medical Supplies" class="btn <?php echo $category_filter == 'Medical Supplies' ? 'btn-primary' : 'btn-info'; ?>">Supplies</a>
            <span style="margin: 0 20px;">|</span>
            <form method="GET" style="display: flex; gap: 10px;">
                <?php if ($category_filter): ?>
                <input type="hidden" name="category" value="<?php echo $category_filter; ?>">
                <?php endif; ?>
                <input type="text" name="search" placeholder="Search products..." value="<?php echo $search; ?>" style="width: 300px;">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if ($search): ?>
                <a href="pharmacy_products.php<?php echo $category_filter ? '?category='.$category_filter : ''; ?>" class="btn btn-info">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <button onclick="document.getElementById('addModal').style.display='block'" class="btn btn-primary">
            + Add New Product
        </button>

        <br><br>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product Name</th>
                    <th>Company</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th>Pack Size</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($products->num_rows > 0) {
                    while($row = $products->fetch_assoc()) {
                        $cat_class = '';
                        switch($row['product_category']) {
                            case 'Medications': $cat_class = 'cat-medications'; break;
                            case 'Medical Equipment': $cat_class = 'cat-equipment'; break;
                            case 'Medical Supplies': $cat_class = 'cat-supplies'; break;
                            // case 'Office Supplies': $cat_class = 'cat-office'; break;
                            // case 'Laboratory Supplies': $cat_class = 'cat-laboratory'; break;
                        }
                        
                        echo "<tr>";
                        echo "<td>" . $row['id'] . "</td>";
                        echo "<td><strong>" . $row['product_name'] . "</strong></td>";
                        echo "<td>" . $row['company_name'] . "</td>";
                        echo "<td><span class='category-badge $cat_class'>" . $row['product_category'] . "</span></td>";
                        echo "<td>" . $row['unit'] . "</td>";
                        echo "<td>" . $row['packing_size'] . "</td>";
                        echo "<td>
                                <button onclick='editProduct(" . json_encode($row) . ")' class='btn btn-warning'>Edit</button>
                                <form method='POST' style='display:inline;'>
                                    <input type='hidden' name='action' value='delete'>
                                    <input type='hidden' name='id' value='" . $row['id'] . "'>
                                    <button type='submit' class='btn btn-danger' onclick='return confirm(\"Remove from catalog?\")'>Delete</button>
                                </form>
                              </td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='7' style='text-align: center;'>No products found</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <!-- Add Product Modal -->
        <div id="addModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="document.getElementById('addModal').style.display='none'">&times;</span>
                <h2>Add New Product to Catalog</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label>Company: *</label>
                        <select name="company_name" required style="width: 100%;">
                            <option value="">Select Company</option>
                            <?php
                            $companies->data_seek(0);
                            while($comp = $companies->fetch_assoc()) {
                                echo "<option value='" . $comp['company_name'] . "'>" . $comp['company_name'] . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Product Category: *</label>
                        <select name="product_category" required style="width: 100%;">
                            <option value="">Select Category</option>
                            <option value="Medications">Medications</option>
                            <option value="Medical Equipment">Medical Equipment</option>
                            <option value="Medical Supplies">Medical Supplies</option>
                            <!-- <option value="Office Supplies">Office Supplies</option>
                            <option value="Laboratory Supplies">Laboratory Supplies</option> -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Product Name: *</label>
                        <input type="text" name="product_name" required style="width: 100%;">
                    </div>
                    <div class="form-group">
                        <label>Unit: *</label>
                        <select name="unit" required style="width: 100%;">
                            <option value="">Select Unit</option>
                            <?php
                            $units->data_seek(0);
                            while($unit = $units->fetch_assoc()) {
                                echo "<option value='" . $unit['unit'] . "'>" . $unit['unit'] . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Packing Size:</label>
                        <input type="text" name="packing_size" placeholder="e.g., 100, 200" style="width: 100%;">
                    </div>
                    <button type="submit" class="btn btn-primary">Add to Catalog</button>
                </form>
            </div>
        </div>

        <!-- Edit Product Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="document.getElementById('editModal').style.display='none'">&times;</span>
                <h2>Edit Product</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="form-group">
                        <label>Product Name:</label>
                        <input type="text" id="edit_product_name" disabled style="background: #ecf0f1; width: 100%;">
                    </div>
                    <div class="form-group">
                        <label>Company:</label>
                        <input type="text" id="edit_company" disabled style="background: #ecf0f1; width: 100%;">
                    </div>
                    <div class="form-group">
                        <label>Unit: *</label>
                        <select name="unit" id="edit_unit" required style="width: 100%;">
                            <?php
                            $units->data_seek(0);
                            while($unit = $units->fetch_assoc()) {
                                echo "<option value='" . $unit['unit'] . "'>" . $unit['unit'] . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Packing Size:</label>
                        <input type="text" name="packing_size" id="edit_packing_size" style="width: 100%;">
                    </div>
                    <button type="submit" class="btn btn-primary">Update Product</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editProduct(product) {
            document.getElementById('edit_id').value = product.id;
            document.getElementById('edit_product_name').value = product.product_name;
            document.getElementById('edit_company').value = product.company_name;
            document.getElementById('edit_unit').value = product.unit;
            document.getElementById('edit_packing_size').value = product.packing_size;
            document.getElementById('editModal').style.display = 'block';
        }

        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>