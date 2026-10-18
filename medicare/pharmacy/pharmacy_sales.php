<?php
session_start();

include("../connection.php");

$success_message = "";
$error_message = "";

// Handle walk-in sales
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_sale'])) {
    $customer_name = $_POST['customer_name'];
    $items = $_POST['items'];
    $payment_method = $_POST['payment_method'];
    $receipt_no = "PHARM-" . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    try {
        $database->begin_transaction();
        
        $total_amount = 0;
        
        // Create billing record
        $insert_bill = "INSERT INTO billing (CustomerName, bill_type, date, bill_no, username, payment_status) 
                        VALUES (?, 'Cash', NOW(), ?, 'Pharmacy', 'paid')";
        $stmt = $database->prepare($insert_bill);
        $stmt->bind_param("ss", $customer_name, $receipt_no);
        $stmt->execute();
        $bill_id = $database->insert_id;
        
        // Process each item
        foreach ($items as $item) {
            $medication_id = $item['medication_id'];
            $quantity = $item['quantity'];
            $price = $item['price'];
            
            // Update stock
            $update_stock = "UPDATE stock_master SET product_quantity = product_quantity - ? WHERE id = ? AND product_quantity >= ?";
            $stmt2 = $database->prepare($update_stock);
            $stmt2->bind_param("iii", $quantity, $medication_id, $quantity);
            $stmt2->execute();
            
            if ($stmt2->affected_rows === 0) {
                throw new Exception("Insufficient stock for item");
            }
            
            // Add to billing details
            $get_medication = "SELECT product_name, product_company, product_unit, packing_size FROM stock_master WHERE id = ?";
            $stmt3 = $database->prepare($get_medication);
            $stmt3->bind_param("i", $medication_id);
            $stmt3->execute();
            $medication = $stmt3->get_result()->fetch_assoc();
            
            $insert_detail = "INSERT INTO billing_details (billing_id, product_company, product_name, product_unit, packing_size, price, quantity) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt4 = $database->prepare($insert_detail);
            $stmt4->bind_param("issssdi", $bill_id, $medication['product_company'], $medication['product_name'], 
                              $medication['product_unit'], $medication['packing_size'], $price, $quantity);
            $stmt4->execute();
            
            $total_amount += ($price * $quantity);
        }
        
        // Create payment record
        $insert_payment = "INSERT INTO billing_payments (pid, receipt_no, type, reference_id, description, amount, payment_method, status, paid_by, payment_date) 
                           VALUES (0, ?, 'pharmacy', ?, 'Walk-in pharmacy sale', ?, ?, 'paid', 'Pharmacy', NOW())";
        $stmt5 = $database->prepare($insert_payment);
        $stmt5->bind_param("sis", $receipt_no, $bill_id, $total_amount, $payment_method);
        $stmt5->execute();
        
        $database->commit();
        $success_message = "Sale completed successfully! Receipt: " . $receipt_no . " | Total: KES " . number_format($total_amount, 2);
        
    } catch (Exception $e) {
        $database->rollback();
        $error_message = "Error processing sale: " . $e->getMessage();
    }
}

// Get available medications
$medications_query = "SELECT sm.id, sm.product_name, sm.product_company, sm.product_quantity, sm.product_selling_price 
                      FROM stock_master sm 
                      WHERE sm.product_quantity > 0 
                      ORDER BY sm.product_name";
$medications_result = $database->query($medications_query);
$available_medications = $medications_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Sales - MediCare</title>
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .container { max-width: 1000px; margin: 0 auto; padding: 20px; }
        .section { background: white; padding: 25px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .alert { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-danger { background: #e74c3c; color: white; }
        .table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table th, .table td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        .table th { background: #3498db; color: white; }
        .sale-item { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 10px; border: 1px solid #dee2e6; }
    </style>
</head>
<body>
    
    <div class="main-content">
        <div class="container">
            <div class="page-header">
                <h1><i class="fas fa-cash-register"></i> Pharmacy Sales</h1>
                <a href="pharmacy.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Pharmacy
                </a>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <!-- Walk-in Sales Form -->
            <div class="section">
                <h3><i class="fas fa-shopping-cart"></i> Process Walk-in Sale</h3>
                <form method="POST" id="saleForm">
                    <input type="hidden" name="process_sale" value="1">
                    
                    <div class="form-group">
                        <label for="customer_name">Customer Name:</label>
                        <input type="text" name="customer_name" id="customer_name" class="form-control" required 
                               placeholder="Enter customer name">
                    </div>
                    
                    <div class="form-group">
                        <label>Sale Items:</label>
                        <div id="saleItems">
                            <!-- Items will be added here dynamically -->
                        </div>
                        <button type="button" class="btn btn-primary" onclick="addSaleItem()">
                            <i class="fas fa-plus"></i> Add Item
                        </button>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_method">Payment Method:</label>
                        <select name="payment_method" id="payment_method" class="form-control" required>
                            <option value="cash">Cash</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="card">Card</option>
                            <option value="insurance">Insurance</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <h4>Total Amount: <span id="totalAmount">KES 0.00</span></h4>
                    </div>
                    
                    <button type="submit" class="btn btn-success" style="width: 100%; padding: 15px;">
                        <i class="fas fa-check"></i> Complete Sale
                    </button>
                </form>
            </div>

            <!-- Available Medications -->
            <div class="section">
                <h3><i class="fas fa-pills"></i> Available Medications</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Medication</th>
                            <th>Company</th>
                            <th>Stock</th>
                            <th>Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($available_medications as $med): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($med['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($med['product_company']); ?></td>
                            <td><?php echo $med['product_quantity']; ?></td>
                            <td>KES <?php echo number_format($med['product_selling_price'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    let itemCount = 0;
    
    function addSaleItem() {
        itemCount++;
        const itemsDiv = document.getElementById('saleItems');
        
        const itemDiv = document.createElement('div');
        itemDiv.className = 'sale-item';
        itemDiv.innerHTML = `
            <div class="form-group">
                <label>Medication:</label>
                <select name="items[${itemCount}][medication_id]" class="form-control medication-select" required onchange="updatePrice(this, ${itemCount})">
                    <option value="">Select Medication</option>
                    <?php foreach($available_medications as $med): ?>
                    <option value="<?php echo $med['id']; ?>" data-price="<?php echo $med['product_selling_price']; ?>" data-stock="<?php echo $med['product_quantity']; ?>">
                        <?php echo htmlspecialchars($med['product_name'] . ' - ' . $med['product_company'] . ' (Stock: ' . $med['product_quantity'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Quantity:</label>
                <input type="number" name="items[${itemCount}][quantity]" class="form-control quantity-input" value="1" min="1" required onchange="updateTotal()">
                <small class="stock-info" style="color: #666;"></small>
            </div>
            <div class="form-group">
                <label>Price:</label>
                <input type="number" name="items[${itemCount}][price]" class="form-control price-input" step="0.01" required readonly>
            </div>
            <button type="button" class="btn btn-danger" onclick="removeItem(this)">
                <i class="fas fa-trash"></i> Remove
            </button>
        `;
        
        itemsDiv.appendChild(itemDiv);
        updateTotal();
    }
    
    function removeItem(button) {
        button.parentElement.remove();
        updateTotal();
    }
    
    function updatePrice(select, index) {
        const selectedOption = select.options[select.selectedIndex];
        const price = selectedOption.getAttribute('data-price');
        const stock = selectedOption.getAttribute('data-stock');
        
        const itemDiv = select.closest('.sale-item');
        const priceInput = itemDiv.querySelector('.price-input');
        const quantityInput = itemDiv.querySelector('.quantity-input');
        const stockInfo = itemDiv.querySelector('.stock-info');
        
        priceInput.value = price;
        stockInfo.textContent = `Available stock: ${stock}`;
        quantityInput.max = stock;
        
        updateTotal();
    }
    
    function updateTotal() {
        let total = 0;
        const priceInputs = document.querySelectorAll('.price-input');
        const quantityInputs = document.querySelectorAll('.quantity-input');
        
        for (let i = 0; i < priceInputs.length; i++) {
            const price = parseFloat(priceInputs[i].value) || 0;
            const quantity = parseInt(quantityInputs[i].value) || 0;
            total += price * quantity;
        }
        
        document.getElementById('totalAmount').textContent = 'KES ' + total.toLocaleString();
    }
    
    // Add first item automatically
    document.addEventListener('DOMContentLoaded', function() {
        addSaleItem();
    });
    
    // Form validation
    document.getElementById('saleForm').addEventListener('submit', function(e) {
        const medicationSelects = document.querySelectorAll('.medication-select');
        let valid = true;
        
        medicationSelects.forEach(select => {
            if (!select.value) {
                valid = false;
                select.style.borderColor = 'red';
            } else {
                select.style.borderColor = '';
            }
        });
        
        if (!valid) {
            e.preventDefault();
            alert('Please select medications for all items');
        }
    });
    </script>
</body>
</html>