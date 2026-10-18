<?php
session_start();

include "admin/connection.php";
include "includes/db_connect.php";
include "includes/sidebar.php";

$message = "";

// Handle receiving purchase
if(isset($_POST['receive_purchase'])) {
    $purchase_id = mysqli_real_escape_string($link, $_POST['purchase_id']);
    $received_quantity = mysqli_real_escape_string($link, $_POST['received_quantity']);
    $notes = mysqli_real_escape_string($link, $_POST['notes']);
    
    // Get purchase details
    $purchase_query = mysqli_query($link, "SELECT * FROM purchase_master WHERE id='$purchase_id' AND status='pending'");
    
    if(mysqli_num_rows($purchase_query) > 0) {
        $purchase = mysqli_fetch_array($purchase_query);
        
        $company_name = $purchase['company_name'];
        $product_name = $purchase['product_name'];
        $unit = $purchase['unit'];
        $packing_size = $purchase['packing_size'];
        $ordered_quantity = $purchase['quantity'];
        $selling_price = $purchase['selling_price'];
        $admin_user = mysqli_real_escape_string($link, $_SESSION['admin']);
        
        // Validate received quantity
        $received_quantity = min($received_quantity, $ordered_quantity);
        
        // Update purchase status
        $update_sql = "UPDATE purchase_master SET 
                      status = 'received', 
                      received_date = NOW(),
                      received_by = '$admin_user',
                      notes = '$notes',
                      quantity = '$received_quantity'
                      WHERE id = '$purchase_id'";
        
        if(mysqli_query($link, $update_sql)) {
            // Update stock
            $check = mysqli_query($link, "SELECT * FROM stock_master WHERE product_name='$product_name' AND product_company='$company_name'");
            
            if(mysqli_num_rows($check) > 0) {
                // Update existing stock
                $stock_sql = "UPDATE stock_master SET 
                            product_quantity = product_quantity + $received_quantity,
                            product_selling_price = '$selling_price' 
                            WHERE product_name='$product_name' AND product_company='$company_name'";
            } else {
                // Add new stock item
                $stock_sql = "INSERT INTO stock_master 
                            (product_company, product_name, product_unit, packing_size, product_quantity, product_selling_price) 
                            VALUES 
                            ('$company_name', '$product_name', '$unit', '$packing_size', '$received_quantity', '$selling_price')";
            }
            
            if(mysqli_query($link, $stock_sql)) {
                $message = "<div class='alert alert-success'>
                            <i class='icon-ok'></i> Purchase #$purchase_id confirmed as received! 
                            Stock updated with $received_quantity units.
                            </div>";
            } else {
                $message = "<div class='alert alert-warning'>
                            <i class='icon-warning-sign'></i> Purchase marked as received but error updating stock: " . mysqli_error($link) . "
                            </div>";
            }
        } else {
            $message = "<div class='alert alert-error'>
                        <i class='icon-remove'></i> Error updating purchase status: " . mysqli_error($link) . "
                        </div>";
        }
    } else {
        $message = "<div class='alert alert-error'>
                    <i class='icon-remove'></i> Purchase not found or already processed!
                    </div>";
    }
}

// Get pending purchases
$pending_query = mysqli_query($link, "SELECT * FROM purchase_master WHERE status='pending' ORDER BY purchase_date DESC");
?>

<div id="content">
    <div id="content-header">
        <div id="breadcrumb">
            <a href="inventorylist.php" title="Go to Home" class="tip-bottom"><i class="icon-home"></i>Home</a>
            <a href="purchase.php" class="tip-bottom">Purchase</a>
            <a href="#" class="current">Pending Purchases</a>
        </div>
    </div>
    
    <div class="container-fluid" style="padding-left: 240px;">
        <?php echo $message; ?>
        
        <div class="row-fluid" style="background-color: white; min-height: 1000px; padding:10px;">
            <div class="span12">
                <div class="widget-box">
                    <div class="widget-title">
                        <span class="icon"><i class="icon-time"></i></span>
                        <h5>Pending Purchase Orders</h5>
                    </div>
                    <div class="widget-content nopadding">
                        <?php if(mysqli_num_rows($pending_query) > 0): ?>
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Order Date</th>
                                    <th>Product</th>
                                    <th>Company</th>
                                    <th>Supplier</th>
                                    <th>Ordered Qty</th>
                                    <th>Unit Price</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = mysqli_fetch_array($pending_query)): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($row['purchase_date'])); ?></td>
                                    <td><strong><?php echo $row['product_name']; ?></strong></td>
                                    <td><?php echo $row['company_name']; ?></td>
                                    <td><?php echo $row['party_name']; ?></td>
                                    <td><?php echo $row['quantity'] . ' ' . $row['unit']; ?></td>
                                    <td>KES <?php echo number_format($row['price'], 2); ?></td>
                                    <td><span class="label label-warning">Pending</span></td>
                                    <td>
                                        <button type="button" class="btn btn-success btn-mini" 
                                                onclick="showReceiveForm(<?php echo $row['id']; ?>, '<?php echo $row['product_name']; ?>', <?php echo $row['quantity']; ?>)">
                                            <i class="icon-check"></i> Receive
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="alert alert-info" style="margin: 20px;">
                            <strong>No pending purchases.</strong> All purchase orders have been received.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Receive Form (hidden by default) -->
<div id="receiveModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 5px; width: 400px;">
        <h3>Confirm Purchase Receipt</h3>
        <form method="POST" id="receiveForm">
            <input type="hidden" name="purchase_id" id="modal_purchase_id">
            
            <div class="control-group">
                <label>Product:</label>
                <input type="text" id="modal_product_name" class="span12" readonly style="background: #f5f5f5;">
            </div>
            
            <div class="control-group">
                <label>Ordered Quantity:</label>
                <input type="text" id="modal_ordered_qty" class="span12" readonly style="background: #f5f5f5;">
            </div>
            
            <div class="control-group">
                <label>Received Quantity: *</label>
                <input type="number" name="received_quantity" id="modal_received_qty" class="span12" min="1" required>
            </div>
            
            <div class="control-group">
                <label>Notes:</label>
                <textarea name="notes" class="span12" rows="3" placeholder="Any remarks about the received batch..."></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" name="receive_purchase" class="btn btn-primary">Confirm Receipt</button>
                <button type="button" onclick="hideReceiveForm()" class="btn">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function showReceiveForm(purchaseId, productName, orderedQty) {
    document.getElementById('modal_purchase_id').value = purchaseId;
    document.getElementById('modal_product_name').value = productName;
    document.getElementById('modal_ordered_qty').value = orderedQty + ' units';
    document.getElementById('modal_received_qty').value = orderedQty;
    document.getElementById('modal_received_qty').max = orderedQty;
    
    document.getElementById('receiveModal').style.display = 'block';
}

function hideReceiveForm() {
    document.getElementById('receiveModal').style.display = 'none';
}
</script>

<?php
include "includes/footer.php";
?>