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

// CRITICAL: Function to check if enough stock is available
function check_stock_availability($product_name, $requested_quantity, $database) {
    $stmt = $database->prepare("SELECT product_quantity FROM stock_master WHERE product_name = ?");
    $stmt->bind_param("s", $product_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if (!$row) {
        return "Product not found in inventory";
    }
    
    $available_quantity = $row['product_quantity'];
    
    if ($available_quantity <= 0) {
        return "Product is OUT OF STOCK (0 available)";
    }
    
    if ($requested_quantity > $available_quantity) {
        return "Insufficient stock. Only $available_quantity available, but you tried to dispense $requested_quantity";
    }
    
    return true; // Stock is available
}

// Function to safely deduct stock
function deduct_stock($product_name, $quantity_to_deduct, $database) {
    // First check availability
    $check = check_stock_availability($product_name, $quantity_to_deduct, $database);
    
    if ($check !== true) {
        return $check; // Return error message
    }
    
    // Safe to deduct
    $stmt = $database->prepare("UPDATE stock_master SET product_quantity = product_quantity - ? WHERE product_name = ?");
    $stmt->bind_param("is", $quantity_to_deduct, $product_name);
    
    if ($stmt->execute()) {
        return true;
    } else {
        return "Error updating stock: " . $database->error;
    }
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'update_price') {
            $id = sanitize_input($_POST['id']);
            $price = sanitize_input($_POST['price']);
            
            $sql = "UPDATE stock_master SET product_selling_price='$price' WHERE id='$id'";
            
            if ($database->query($sql)) {
                $message = "<p style='color: green;'>Price updated successfully!</p>";
            } else {
                $message = "<p style='color: red;'>Error: " . $database->error . "</p>";
            }
        }
        
        if ($action == 'delete_stock') {
            $id = sanitize_input($_POST['id']);
            
            // Check if stock quantity is 0 before deleting
            $check_stmt = $database->prepare("SELECT product_quantity FROM stock_master WHERE id = ?");
            $check_stmt->bind_param("i", $id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['product_quantity'] > 0) {
                $message = "<p style='color: red;'>Error: Cannot delete item with stock remaining. Please sell or transfer stock first.</p>";
            } else {
                $sql = "DELETE FROM stock_master WHERE id='$id'";
                if ($database->query($sql)) {
                    $message = "<p style='color: green;'>Stock item deleted successfully!</p>";
                } else {
                    $message = "<p style='color: red;'>Error: " . $database->error . "</p>";
                }
            }
        }
    }
}

// Filter functionality
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

$where_clause = "";
if ($filter == 'low') {
    $where_clause = "WHERE product_quantity < 10 AND product_quantity > 0"; // Exclude out-of-stock
} elseif ($filter == 'out') {
    $where_clause = "WHERE product_quantity = 0";
}

if ($search) {
    $search_condition = " (product_name LIKE '%$search%' OR product_company LIKE '%$search%')";
    $where_clause = $where_clause ? "$where_clause AND $search_condition" : "WHERE $search_condition";
}

// Get inventory
$inventory = $database->query("
    SELECT * FROM stock_master 
    $where_clause
    ORDER BY product_name
");

// Get statistics
$total_items = $database->query("SELECT COUNT(*) as count FROM stock_master")->fetch_assoc()['count'];
$low_stock = $database->query("SELECT COUNT(*) as count FROM stock_master WHERE product_quantity < 10 AND product_quantity > 0")->fetch_assoc()['count'];
$out_of_stock = $database->query("SELECT COUNT(*) as count FROM stock_master WHERE product_quantity = 0")->fetch_assoc()['count'];
$total_value = $database->query("SELECT SUM(product_quantity * product_selling_price) as total FROM stock_master")->fetch_assoc()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">  
    <link rel="stylesheet" href="../css/main.css">  
    <title>Inventory Management - MediAccess</title>
    <style>
        /* [Keep all your existing CSS here] */
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
        .alert-card {
            background: #e74c3c;
            color: white;
        }
        .alert-card h2 {
            color: white;
        }
        .success-card {
            background: #27ae60;
            color: white;
        }
        .success-card h2 {
            color: white;
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
        input[type="text"], input[type="number"] {
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
        .low-stock {
            color: #e74c3c;
            font-weight: bold;
        }
        .out-of-stock {
            color: #c0392b;
            font-weight: bold;
        }
        .in-stock {
            color: #27ae60;
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
            max-width: 500px;
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
        .error-alert {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success-alert {
            background: #efe;
            border: 1px solid #cfc;
            color: #3c3;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
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
            <!-- <a href="pharmacy_dispense.php">Dispense</a> -->
        </div>

        <h1>Inventory Management</h1>
        <?php echo $message; ?>

        <div class="stats-container">
            <div class="stat-card success-card">
                <p>Total Items</p>
                <h2><?php echo $total_items; ?></h2>
            </div>
            <div class="stat-card alert-card">
                <p>Low Stock (< 10)</p>
                <h2><?php echo $low_stock; ?></h2>
            </div>
            <div class="stat-card" style="background: #c0392b; color: white;">
                <p>Out of Stock (= 0)</p>
                <h2 style="color: white;"><?php echo $out_of_stock; ?></h2>
            </div>
            <div class="stat-card">
                <p>Total Inventory Value</p>
                <h2>KES <?php echo number_format($total_value, 2); ?></h2>
            </div>
        </div>

        <div class="filter-container">
            <a href="pharmacy_inventory.php" class="btn <?php echo $filter == 'all' ? 'btn-primary' : 'btn-info'; ?>">All Items</a>
            <a href="pharmacy_inventory.php?filter=low" class="btn <?php echo $filter == 'low' ? 'btn-primary' : 'btn-info'; ?>">Low Stock</a>
            <a href="pharmacy_inventory.php?filter=out" class="btn <?php echo $filter == 'out' ? 'btn-primary' : 'btn-info'; ?>">Out of Stock</a>
            <span style="margin: 0 20px;">|</span>
            <form method="GET" style="display: flex; gap: 10px;">
                <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                <input type="text" name="search" placeholder="Search product or company..." value="<?php echo $search; ?>" style="width: 300px;">
                <button type="submit" class="btn btn-primary">Search</button>
                <?php if ($search): ?>
                <a href="pharmacy_inventory.php?filter=<?php echo $filter; ?>" class="btn btn-info">Clear</a>
                <?php endif; ?>
            </form>
            <a href="pharmacy_purchase.php" class="btn btn-warning" style="margin-left: auto;">+ Purchase Stock</a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Company</th>
                    <th>Unit</th>
                    <th>Pack Size</th>
                    <th>Quantity</th>
                    <th>Status</th>
                    <th>Selling Price</th>
                    <th>Total Value</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($inventory->num_rows > 0) {
                    while($row = $inventory->fetch_assoc()) {
                        $quantity = $row['product_quantity'];
                        $status_class = '';
                        $status_text = '';
                        
                        // FIXED: Proper status logic
                        if ($quantity == 0) {
                            $status_class = 'out-of-stock';
                            $status_text = 'OUT OF STOCK';
                        } elseif ($quantity < 10) {
                            $status_class = 'low-stock';
                            $status_text = 'LOW STOCK';
                        } else {
                            $status_class = 'in-stock';
                            $status_text = 'IN STOCK';
                        }
                        
                        $total_value = $quantity * $row['product_selling_price'];
                        
                        echo "<tr>";
                        echo "<td><strong>" . htmlspecialchars($row['product_name']) . "</strong></td>";
                        echo "<td>" . htmlspecialchars($row['product_company']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['product_unit']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['packing_size']) . "</td>";
                        echo "<td class='$status_class'>" . $quantity . "</td>";
                        echo "<td><span class='$status_class'>$status_text</span></td>";
                        echo "<td>KES " . number_format($row['product_selling_price'], 2) . "</td>";
                        echo "<td>KES " . number_format($total_value, 2) . "</td>";
                        echo "<td>
                                <button onclick='updatePrice(" . json_encode($row) . ")' class='btn btn-warning'>Update Price</button>
                                <a href='pharmacy_purchase.php?product=" . urlencode($row['product_name']) . "' class='btn btn-info'>Restock</a>";
                        
                        // Only show delete button if out of stock
                        if ($quantity <= 0) {
                            echo "<form method='POST' style='display:inline;' onsubmit=\"return confirm('Delete this item?')\">
                                    <input type='hidden' name='action' value='delete_stock'>
                                    <input type='hidden' name='id' value='" . $row['id'] . "'>
                                    <button type='submit' class='btn btn-danger'>Delete</button>
                                  </form>";
                        }
                        
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='9' style='text-align: center; padding: 20px;'><div class='empty-state'><i class='fas fa-inbox'></i><p>No items found</p></div></td></tr>";
                }
                ?>
            </tbody>
        </table>

        <!-- Update Price Modal -->
        <div id="priceModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="document.getElementById('priceModal').style.display='none'">&times;</span>
                <h2>Update Selling Price</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_price">
                    <input type="hidden" name="id" id="price_id">
                    <div class="form-group">
                        <label>Product:</label>
                        <input type="text" id="price_product" disabled style="background: #ecf0f1; width: 100%; padding: 10px; border-radius: 5px;">
                    </div>
                    <div class="form-group">
                        <label>Current Price:</label>
                        <input type="text" id="price_current" disabled style="background: #ecf0f1; width: 100%; padding: 10px; border-radius: 5px;">
                    </div>
                    <div class="form-group">
                        <label>New Selling Price (KES):</label>
                        <input type="number" name="price" id="price_new" step="0.01" required style="width: 100%; padding: 10px; border: 1px solid #bdc3c7; border-radius: 5px;">
                    </div>
                    <button type="submit" class="btn btn-primary">Update Price</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function updatePrice(product) {
            document.getElementById('price_id').value = product.id;
            document.getElementById('price_product').value = product.product_name + ' (' + product.product_company + ')';
            document.getElementById('price_current').value = 'KES ' + parseFloat(product.product_selling_price).toFixed(2);
            document.getElementById('price_new').value = product.product_selling_price;
            document.getElementById('priceModal').style.display = 'block';
        }

        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>