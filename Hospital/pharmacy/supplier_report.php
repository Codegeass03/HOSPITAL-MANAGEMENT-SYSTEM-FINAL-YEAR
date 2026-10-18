<?php
session_start();
// if(!isset($_SESSION["admin"])) {
//     header("Location: index.php");
//     exit();
// }

include "admin/connection.php";
include "includes/db_connect.php";
include "includes/sidebar.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Purchase Records - AuraCare HMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            margin: 0;
        }

        .main-content {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            margin-left: 280px;
            width: calc(100% - 280px);
            position: relative;
            z-index: 1;
        }

        /* Header Section */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            gap: 1rem;
        }

        .header-content h1 {
            font-size: 2rem;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .header-content p {
            color: #64748b;
            font-size: 0.95rem;
        }

        .btn-add-supplier {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            border: none;
            padding: 0.875rem 1.75rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            white-space: nowrap;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }

        .btn-add-supplier:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(14, 165, 233, 0.4);
        }

        /* Search and Filter Section */
        .search-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .search-controls {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: 1rem;
            align-items: flex-end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
        }

        .form-group select,
        .form-group input {
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background-color: #f8fafc;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #0ea5e9;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }

        .btn-search {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-clear {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-clear:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
        }

        /* Results Section */
        .results-section {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-top: 2rem;
        }

        .results-header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            color: white;
            padding: 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead tr {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        thead th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #334155;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr {
            border-bottom: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            background-color: #f1f5f9;
        }

        tbody td {
            padding: 1rem;
            color: #475569;
            font-size: 0.95rem;
        }

        .no-results {
            padding: 3rem;
            text-align: center;
            color: #94a3b8;
        }

        .no-results i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
            background-color: rgba(0, 0, 0, 0.6);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.75rem;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .modal-close:hover {
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-grid.full {
            grid-column: 1 / -1;
        }

        .form-group input {
            width: 100%;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            padding: 1.5rem 2rem;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }

        .btn-cancel {
            background: #e2e8f0;
            color: #334155;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-cancel:hover {
            background: #cbd5e1;
        }

        .btn-submit {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .search-controls {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 0.85rem;
            }

            tbody td {
                padding: 0.75rem;
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <h1><i class="fas fa-truck"></i> Supplier Purchase Records</h1>
                <p>Manage and track all supplier purchases</p>
            </div>
            <!-- <button class="btn-add-supplier" onclick="window.location.href='supplier2.php'">
                <i class="fas fa-plus"></i> Add New Supplier
            </button> -->
        </div>

        <!-- Search Section -->
        <div class="search-section">
            <form class="search-controls" name="form1" method="post" action="">
                <div class="form-group">
                    <label for="company_name"><i class="fas fa-building"></i> Select Company</label>
                    <select class="form-control" name="company_name" id="company_name">
                        <option value="">-- All Companies --</option>
                        <?php 
                        $res = mysqli_query($link, "SELECT DISTINCT businessname FROM suppliers ORDER BY businessname");
                        while($row = mysqli_fetch_array($res)) {
                            echo "<option>" . htmlspecialchars($row["businessname"]) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <button type="submit" name="submit1" class="btn-search">
                    <i class="fas fa-search"></i> Search
                </button>
                <button type="button" name="submit2" class="btn-clear" onclick="window.location.href=window.location.href">
                    <i class="fas fa-times"></i> Clear
                </button>
            </form>
        </div>

        <!-- Results Section -->
        <?php
        if(isset($_POST["submit1"]) && !empty($_POST["company_name"])) {
            $company_name = htmlspecialchars($_POST["company_name"]);
            $res = mysqli_query($link, "SELECT * FROM purchase_master WHERE party_name='$company_name' ORDER BY purchase_date DESC");
            $row_count = mysqli_num_rows($res);
        ?>
            <div class="results-section">
                <div class="results-header">
                    <i class="fas fa-list"></i> Search Results (<?php echo $row_count; ?> records found)
                </div>
                <?php if($row_count > 0) { ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Company Name</th>
                                <th>Product Name</th>
                                <th>Unit</th>
                                <th>Packing Size</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Purchase Method</th>
                                <th>Expiry Date</th>
                                <th>Purchase Date</th>
                                <th>Username</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            while ($row = mysqli_fetch_array($res)) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row["company_name"] ?? '') . "</td>";
                                echo "<td>" . htmlspecialchars($row["product_name"] ?? '') . "</td>";
                                echo "<td>" . htmlspecialchars($row["unit"] ?? '') . "</td>";
                                echo "<td>" . htmlspecialchars($row["packing_size"] ?? '') . "</td>";
                                echo "<td>" . htmlspecialchars($row["quantity"] ?? '') . "</td>";
                                echo "<td>" . number_format($row["price"] ?? 0, 2) . "</td>";
                                echo "<td>" . htmlspecialchars($row["purchase_method"] ?? '') . "</td>";
                                echo "<td>" . htmlspecialchars($row["expiry_date"] ?? '') . "</td>";
                                echo "<td>" . date('M d, Y', strtotime($row["purchase_date"] ?? '')) . "</td>";
                                echo "<td>" . htmlspecialchars($row["username"] ?? '') . "</td>";
                                echo "</tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php } else { ?>
                <div class="no-results">
                    <i class="fas fa-inbox"></i>
                    <p>No purchase records found for this company.</p>
                </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>

    <!-- Add Supplier Modal -->
    <div id="addSupplierModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-truck"></i> Add New Supplier</h2>
                <button class="modal-close" onclick="window.location.href='supplier2.php'">&times;</button>
            </div>
            <form id="supplierPurchaseForm">
                <div class="modal-body">
                    <div class="form-grid">
                        <!-- Supplier Information Section -->
                        <div class="form-group full-width" style="margin-bottom: 1rem;">
                            <h4 style="margin: 0 0 1rem 0; color: #334155; font-size: 0.95rem; font-weight: 600; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">
                                <i class="fas fa-building"></i> Supplier Information
                            </h4>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Company Name *</label>
                            <input type="text" name="company_name" class="form-input" placeholder="Enter company name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Person *</label>
                            <input type="text" name="contact_person" class="form-input" placeholder="Contact person name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-input" placeholder="supplier@company.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone No. *</label>
                            <input type="tel" name="phone" class="form-input" placeholder="Enter phone number" required>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-input" placeholder="Enter supplier address">
                        </div>

                        <!-- Product Information Section -->
                        <div class="form-group full-width" style="margin-top: 1.5rem; margin-bottom: 1rem;">
                            <h4 style="margin: 0 0 1rem 0; color: #334155; font-size: 0.95rem; font-weight: 600; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">
                                <i class="fas fa-box"></i> Product Details
                            </h4>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Product Name *</label>
                            <input type="text" name="product_name" class="form-input" placeholder="Enter product name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-input">
                                <option value="">Select category</option>
                                <option value="Medications">Medications</option>
                                <option value="Medical Supplies">Medical Supplies</option>
                                <option value="Equipment">Equipment</option>
                                <option value="Consumables">Consumables</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Unit *</label>
                            <input type="text" name="unit" class="form-input" placeholder="e.g., Box, Carton, Pcs" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Packing Size *</label>
                            <input type="text" name="packing_size" class="form-input" placeholder="e.g., 10x100ml, 50 tabs" required>
                        </div>

                        <!-- Purchase Information Section -->
                        <div class="form-group full-width" style="margin-top: 1.5rem; margin-bottom: 1rem;">
                            <h4 style="margin: 0 0 1rem 0; color: #334155; font-size: 0.95rem; font-weight: 600; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">
                                <i class="fas fa-shopping-cart"></i> Purchase Details
                            </h4>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Quantity *</label>
                            <input type="number" name="quantity" class="form-input" placeholder="Enter quantity" min="1" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Price per Unit *</label>
                            <input type="number" name="price" class="form-input" placeholder="0.00" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Purchase Method *</label>
                            <select name="purchase_method" class="form-input" required>
                                <option value="">Select method</option>
                                <option value="Cash">Cash</option>
                                <option value="Credit">Credit</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Purchase Date *</label>
                            <input type="date" name="purchase_date" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Expiry Date *</label>
                            <input type="date" name="expiry_date" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Invoice Number</label>
                            <input type="text" name="invoice_number" class="form-input" placeholder="Enter invoice number">
                        </div>

                        <!-- Additional Information Section -->
                        <div class="form-group full-width" style="margin-top: 1.5rem; margin-bottom: 1rem;">
                            <h4 style="margin: 0 0 1rem 0; color: #334155; font-size: 0.95rem; font-weight: 600; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">
                                <i class="fas fa-info-circle"></i> Additional Information
                            </h4>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-input" placeholder="Add any additional notes about this purchase" style="min-height: 80px; resize: vertical;"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeAddSupplierModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Save Supplier Purchase</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddSupplierModal() {
            document.getElementById('addSupplierModal').style.display = 'block';
        }

        function closeAddSupplierModal() {
            document.getElementById('addSupplierModal').style.display = 'none';
            document.getElementById('supplierPurchaseForm').reset();
        }

        window.onclick = function(event) {
            const modal = document.getElementById('addSupplierModal');
            if (event.target === modal) {
                closeAddSupplierModal();
            }
        }

        document.getElementById('supplierPurchaseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Supplier purchase form would be submitted here');
            closeAddSupplierModal();
        });
    </script>
</body>
</html>

<?php 
include "includes/footer.php";
?>