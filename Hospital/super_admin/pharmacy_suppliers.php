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
            $firstname = sanitize_input($_POST['firstname']);
            $lastname = sanitize_input($_POST['lastname']);
            $businessname = sanitize_input($_POST['businessname']);
            $productcategory = sanitize_input($_POST['productcategory']);
            $contact = sanitize_input($_POST['contact']);
            $address = sanitize_input($_POST['address']);
            $city = sanitize_input($_POST['city']);
            
            // Check if business name already exists
            $check = $database->query("SELECT * FROM suppliers WHERE businessname='$businessname'");
            if ($check->num_rows > 0) {
                $message = "<p style='color: red;'>Supplier with this business name already exists!</p>";
            } else {
                $sql = "INSERT INTO suppliers (firstname, lastname, businessname, productcategory, contact, address, city) 
                        VALUES ('$firstname', '$lastname', '$businessname', '$productcategory', '$contact', '$address', '$city')";
                
                if ($database->query($sql)) {
                    $message = "<p style='color: green;'>Supplier added successfully!</p>";
                } else {
                    $message = "<p style='color: red;'>Error: " . $database->error . "</p>";
                }
            }
        }
        
        if ($action == 'delete') {
            $id = sanitize_input($_POST['id']);
            
            // Check if supplier has purchases
            $businessname = $database->query("SELECT businessname FROM suppliers WHERE id='$id'")->fetch_assoc()['businessname'];
            $check = $database->query("SELECT COUNT(*) as count FROM purchase_master WHERE party_name='$businessname'")->fetch_assoc()['count'];
            
            if ($check > 0) {
                $message = "<p style='color: red;'>Cannot delete! This supplier has $check purchase record(s).</p>";
            } else {
                $sql = "DELETE FROM suppliers WHERE id='$id'";
                if ($database->query($sql)) {
                    $message = "<p style='color: green;'>Supplier deleted successfully!</p>";
                } else {
                    $message = "<p style='color: red;'>Error: " . $database->error . "</p>";
                }
            }
        }
        
        if ($action == 'edit') {
            $id = sanitize_input($_POST['id']);
            $firstname = sanitize_input($_POST['firstname']);
            $lastname = sanitize_input($_POST['lastname']);
            $productcategory = sanitize_input($_POST['productcategory']);
            $contact = sanitize_input($_POST['contact']);
            $address = sanitize_input($_POST['address']);
            $city = sanitize_input($_POST['city']);
            
            $sql = "UPDATE suppliers SET firstname='$firstname', lastname='$lastname', 
                    productcategory='$productcategory', contact='$contact', address='$address', city='$city' 
                    WHERE id='$id'";
            
            if ($database->query($sql)) {
                $message = "<p style='color: green;'>Supplier updated successfully!</p>";
            } else {
                $message = "<p style='color: red;'>Error: " . $database->error . "</p>";
            }
        }
    }
    
    // Handle company name submission
    if (isset($_POST["submit_company"])) {
        $company_name = sanitize_input($_POST["company_name"]);
        
        // Check if company already exists
        $check_sql = "SELECT id FROM company_name WHERE company_name = '$company_name'";
        $result = $database->query($check_sql);
        
        if ($result->num_rows > 0) {
            $message = "<p style='color: orange;'>⚠️ Company already exists in the database.</p>";
        } else {
            // Insert into company_name table - ONLY company_name column
            $insert_sql = "INSERT INTO company_name (company_name) VALUES ('$company_name')";
            
            if ($database->query($insert_sql)) {
                $message = "<p style='color: green;'>✅ Company added successfully!</p>";
            } else {
                $message = "<p style='color: red;'>Error adding company: " . $database->error . "</p>";
            }
        }
    }
}

// Search functionality
$search = "";
if (isset($_GET['search'])) {
    $search = sanitize_input($_GET['search']);
    $suppliers = $database->query("
        SELECT * FROM suppliers 
        WHERE businessname LIKE '%$search%' 
        OR firstname LIKE '%$search%' 
        OR lastname LIKE '%$search%'
        OR productcategory LIKE '%$search%'
        OR city LIKE '%$search%'
        ORDER BY businessname
    ");
} else {
    $suppliers = $database->query("SELECT * FROM suppliers ORDER BY businessname");
}

// Get companies for dropdown
$companies = $database->query("SELECT DISTINCT company_name FROM company_name ORDER BY company_name");

// Get product categories for dropdown
$categories = $database->query("SELECT DISTINCT productcategory FROM suppliers WHERE productcategory IS NOT NULL AND productcategory != '' ORDER BY productcategory");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">  
    <link rel="stylesheet" href="../css/main.css">  
    <title>Supplier Management - MediAccess</title>
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
        .search-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #2c3e50;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #bdc3c7;
            border-radius: 5px;
            font-size: 14px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
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
        .btn-success {
            background: #2ecc71;
            color: white;
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
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
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
        .close:hover {
            color: #e74c3c;
        }
        .category-badge {
            background: #3498db;
            color: white;
            padding: 3px 10px;
            border-radius: 3px;
            font-size: 12px;
        }
        .required {
            color: #e74c3c;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
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

        <h1>Supplier Management</h1>
        <?php echo $message; ?>

        <div class="search-container">
            <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                <input type="text" name="search" placeholder="Search by business name, contact person, category, or city..." value="<?php echo $search; ?>" style="flex: 1;">
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="pharmacy_suppliers.php" class="btn btn-info">Clear</a>
            </form>
        </div>

        <div class="action-buttons">
            <button onclick="document.getElementById('addSupplierModal').style.display='block'" class="btn btn-primary">
                + Add New Supplier
            </button>
            <button onclick="document.getElementById('addCompanyModal').style.display='block'" class="btn btn-success">
                + Add Company Name
            </button>
        </div>

        <br><br>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Business Name</th>
                    <th>Contact Person</th>
                    <th>Category</th>
                    <th>Phone</th>
                    <th>City</th>
                    <th>Address</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($suppliers->num_rows > 0) {
                    while($row = $suppliers->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . $row['id'] . "</td>";
                        echo "<td><strong>" . $row['businessname'] . "</strong></td>";
                        echo "<td>" . $row['firstname'] . " " . $row['lastname'] . "</td>";
                        echo "<td><span class='category-badge'>" . $row['productcategory'] . "</span></td>";
                        echo "<td>" . $row['contact'] . "</td>";
                        echo "<td>" . $row['city'] . "</td>";
                        echo "<td>" . substr($row['address'], 0, 30) . "...</td>";
                        echo "<td>
                                <button onclick='viewSupplier(" . json_encode($row) . ")' class='btn btn-info'>View</button>
                                <button onclick='editSupplier(" . json_encode($row) . ")' class='btn btn-warning'>Edit</button>
                                <form method='POST' style='display:inline;'>
                                    <input type='hidden' name='action' value='delete'>
                                    <input type='hidden' name='id' value='" . $row['id'] . "'>
                                    <button type='submit' class='btn btn-danger' onclick='return confirm(\"Are you sure?\")'>Delete</button>
                                </form>
                              </td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='8' style='text-align: center;'>No suppliers found</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <!-- Add Company Modal -->
        <div id="addCompanyModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="document.getElementById('addCompanyModal').style.display='none'">&times;</span>
                <h2>Add New Company</h2>
                <form name="form2" method="post" class="form-horizontal">
                    <input type="hidden" name="submit_company" value="1">
                    
                    <div class="form-group">
                        <label>Company Name: <span class="required">*</span></label>
                        <input type="text" placeholder="Enter company name" name="company_name" required />
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Save Company</button>
                        <button type="button" class="btn btn-info" onclick="document.getElementById('addCompanyModal').style.display='none'">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Supplier Modal -->
        <div id="addSupplierModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="document.getElementById('addSupplierModal').style.display='none'">&times;</span>
                <h2>Add New Supplier</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label>Business Name: *</label>
                        <input type="text" name="businessname" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Person First Name: *</label>
                        <input type="text" name="firstname" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Person Last Name: *</label>
                        <input type="text" name="lastname" required>
                    </div>
                    <div class="form-group">
                        <label>Product Category: *</label>
                        <select name="productcategory" required>
                            <option value="">Select Category</option>
                            <option value="Medications">Medications</option>
                            <option value="Medical Equipment">Medical Equipment</option>
                            <option value="Medical Supplies">Medical Supplies</option>
                            <!-- <option value="Office Supplies">Office Supplies</option>
                            <option value="Laboratory Supplies">Laboratory Supplies</option> -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contact Phone: *</label>
                        <input type="tel" name="contact" pattern="[0][0-9]{9}" placeholder="0712345678" required>
                    </div>
                    <div class="form-group">
                        <label>City: *</label>
                        <input type="text" name="city" required>
                    </div>
                    <div class="form-group">
                        <label>Address: *</label>
                        <textarea name="address" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Supplier</button>
                    <button type="button" class="btn btn-info" onclick="document.getElementById('addSupplierModal').style.display='none'">Cancel</button>
                </form>
            </div>
        </div>

        <!-- View Supplier Modal -->
        <div id="viewModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="document.getElementById('viewModal').style.display='none'">&times;</span>
                <h2>Supplier Details</h2>
                <div id="supplierDetails"></div>
                <br>
                <button type="button" class="btn btn-info" onclick="document.getElementById('viewModal').style.display='none'">Close</button>
            </div>
        </div>

        <!-- Edit Supplier Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="document.getElementById('editModal').style.display='none'">&times;</span>
                <h2>Edit Supplier</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="form-group">
                        <label>Business Name:</label>
                        <input type="text" id="edit_businessname" disabled style="background: #ecf0f1;">
                    </div>
                    <div class="form-group">
                        <label>Contact Person First Name:</label>
                        <input type="text" name="firstname" id="edit_firstname" required>
                    </div>
                    <div class="form-group">
                        <label>Contact Person Last Name:</label>
                        <input type="text" name="lastname" id="edit_lastname" required>
                    </div>
                    <div class="form-group">
                        <label>Product Category:</label>
                        <select name="productcategory" id="edit_productcategory" required>
                            <option value="Medications">Medications</option>
                            <option value="Medical Equipment">Medical Equipment</option>
                            <option value="Medical Supplies">Medical Supplies</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contact Phone:</label>
                        <input type="tel" name="contact" id="edit_contact" required>
                    </div>
                    <div class="form-group">
                        <label>City:</label>
                        <input type="text" name="city" id="edit_city" required>
                    </div>
                    <div class="form-group">
                        <label>Address:</label>
                        <textarea name="address" id="edit_address" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Supplier</button>
                    <button type="button" class="btn btn-info" onclick="document.getElementById('editModal').style.display='none'">Cancel</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function viewSupplier(supplier) {
            const details = `
                <p><strong>Business Name:</strong> ${supplier.businessname}</p>
                <p><strong>Contact Person:</strong> ${supplier.firstname} ${supplier.lastname}</p>
                <p><strong>Product Category:</strong> ${supplier.productcategory}</p>
                <p><strong>Phone:</strong> ${supplier.contact}</p>
                <p><strong>City:</strong> ${supplier.city}</p>
                <p><strong>Address:</strong> ${supplier.address}</p>
            `;
            document.getElementById('supplierDetails').innerHTML = details;
            document.getElementById('viewModal').style.display = 'block';
        }

        function editSupplier(supplier) {
            document.getElementById('edit_id').value = supplier.id;
            document.getElementById('edit_businessname').value = supplier.businessname;
            document.getElementById('edit_firstname').value = supplier.firstname;
            document.getElementById('edit_lastname').value = supplier.lastname;
            document.getElementById('edit_productcategory').value = supplier.productcategory;
            document.getElementById('edit_contact').value = supplier.contact;
            document.getElementById('edit_city').value = supplier.city;
            document.getElementById('edit_address').value = supplier.address;
            document.getElementById('editModal').style.display = 'block';
        }

        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    modal.style.display = 'none';
                });
            }
        });
    </script>
</body>
</html>