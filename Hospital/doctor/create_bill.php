<?php
session_start();
include "includes/db_connect.php";
include "includes/sidebar.php";

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $patient_id = $_POST['patient_id'];
        $bill_date = $_POST['bill_date'];
        $payment_method = $_POST['payment_method'];
        $items = $_POST['items'];
        
        // Generate bill number
        $bill_number = 'BIL-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Calculate totals
        $total_amount = 0;
        foreach ($items as $item) {
            $total_amount += $item['total'];
        }
        
        $paid_amount = $_POST['paid_amount'];
        $balance_amount = $total_amount - $paid_amount;
        $status = $balance_amount > 0 ? 'pending' : 'paid';
        
        // Insert bill
        $stmt = $pdo->prepare("
            INSERT INTO billing (bill_number, patient_id, bill_date, total_amount, paid_amount, balance_amount, payment_method, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$bill_number, $patient_id, $bill_date, $total_amount, $paid_amount, $balance_amount, $payment_method, $status]);
        $bill_id = $pdo->lastInsertId();
        
        // Insert bill items
        $stmt = $pdo->prepare("
            INSERT INTO bill_items (bill_id, item_description, quantity, unit_price, total_amount, department)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($items as $item) {
            $stmt->execute([
                $bill_id,
                $item['description'],
                $item['quantity'],
                $item['unit_price'],
                $item['total'],
                $item['department']
            ]);
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = "Bill created successfully! Bill Number: $bill_number";
        header("Location: billing_dashboard.php");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error creating bill: " . $e->getMessage();
    }
}

// Get patients for dropdown
$patients = $pdo->query("SELECT id, patient_id, first_name, last_name FROM patients ORDER BY first_name")->fetchAll();

// Get services
$services = [
    ['id' => 1, 'name' => 'Consultation', 'price' => 500, 'department' => 'OPD'],
    ['id' => 2, 'name' => 'Laboratory Test', 'price' => 1500, 'department' => 'Laboratory'],
    ['id' => 3, 'name' => 'X-Ray', 'price' => 2000, 'department' => 'Radiology'],
    ['id' => 4, 'name' => 'Ultrasound', 'price' => 3000, 'department' => 'Radiology'],
    ['id' => 5, 'name' => 'Dressing', 'price' => 300, 'department' => 'OPD'],
    ['id' => 6, 'name' => 'Injection', 'price' => 200, 'department' => 'OPD']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Bill - Equity Afia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 260px;
            padding: 2rem;
            background: #f8fafc;
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        .page-header {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .page-header h1 {
            margin: 0;
            color: #1f2937;
            font-size: 1.875rem;
            font-weight: 700;
        }
        
        .page-header p {
            margin: 0.5rem 0 0 0;
            color: #6b7280;
        }
        
        .bill-form {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .items-section {
            margin: 2rem 0;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid #d1d5db;
            color: #374151;
        }
        
        .btn-outline:hover {
            background: #f9fafb;
        }
        
        .item-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 0.5rem;
        }
        
        .summary-card {
            background: #f0f9ff;
            padding: 1.5rem;
            border-radius: 0.75rem;
            border-left: 4px solid #3b82f6;
            margin-top: 2rem;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e1f5fe;
        }
        
        .summary-total {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .service-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
        </div>
        <?php endif; ?>
        
        <div class="page-header">
            <h1><i class="fas fa-file-invoice"></i> Create New Bill</h1>
            <p>Create billing for hospital services and procedures</p>
        </div>
        
        <form method="POST" class="bill-form">
            <div class="form-grid">
                <div class="form-group">
                    <label for="patient_id">Patient</label>
                    <select class="form-control" id="patient_id" name="patient_id" required>
                        <option value="">Select Patient</option>
                        <?php foreach ($patients as $patient): ?>
                        <option value="<?php echo $patient['id']; ?>">
                            <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' (' . $patient['patient_id'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="bill_date">Bill Date</label>
                    <input type="datetime-local" class="form-control" id="bill_date" name="bill_date" 
                           value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="payment_method">Payment Method</label>
                    <select class="form-control" id="payment_method" name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="mpesa">M-Pesa</option>
                        <option value="insurance">Insurance</option>
                        <option value="card">Credit/Debit Card</option>
                        <option value="bank">Bank Transfer</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="paid_amount">Amount Paid</label>
                    <input type="number" class="form-control" id="paid_amount" name="paid_amount" 
                           step="0.01" min="0" value="0" required>
                </div>
            </div>
            
            <div class="items-section">
                <div class="section-header">
                    <h2 class="section-title">Bill Items</h2>
                    <button type="button" class="btn btn-outline" onclick="addItemRow()">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
                
                <div id="items-container">
                    <div class="item-row">
                        <div class="form-group">
                            <label>Service/Item</label>
                            <select class="service-select" name="items[0][description]" onchange="updateItemPrice(0)" required>
                                <option value="">Select Service</option>
                                <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['name']; ?>" 
                                        data-price="<?php echo $service['price']; ?>"
                                        data-department="<?php echo $service['department']; ?>">
                                    <?php echo $service['name']; ?> - Ksh <?php echo number_format($service['price'], 2); ?>
                                </option>
                                <?php endforeach; ?>
                                <option value="custom">Custom Item</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" class="form-control" name="items[0][department]" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="number" class="form-control" name="items[0][quantity]" 
                                   min="1" value="1" onchange="calculateTotal(0)" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Unit Price</label>
                            <input type="number" class="form-control" name="items[0][unit_price]" 
                                   step="0.01" min="0" onchange="calculateTotal(0)" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Total</label>
                            <input type="number" class="form-control" name="items[0][total]" readonly>
                        </div>
                        
                        <div class="form-group">
                            <button type="button" class="btn btn-danger" onclick="removeItemRow(this)" disabled>
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="summary-card">
                <div class="summary-row">
                    <strong>Subtotal:</strong>
                    <span id="subtotal">Ksh 0.00</span>
                </div>
                <div class="summary-row">
                    <strong>Amount Paid:</strong>
                    <span id="display-paid">Ksh 0.00</span>
                </div>
                <div class="summary-row summary-total">
                    <strong>Balance Due:</strong>
                    <span id="balance-due">Ksh 0.00</span>
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Bill
                </button>
                <button type="button" class="btn btn-outline" onclick="window.history.back()">
                    <i class="fas fa-arrow-left"></i> Cancel
                </button>
            </div>
        </form>
    </div>

    <script>
        let itemCount = 1;
        
        function addItemRow() {
            const container = document.getElementById('items-container');
            const newRow = document.createElement('div');
            newRow.className = 'item-row';
            newRow.innerHTML = `
                <div class="form-group">
                    <label>Service/Item</label>
                    <select class="service-select" name="items[${itemCount}][description]" onchange="updateItemPrice(${itemCount})" required>
                        <option value="">Select Service</option>
                        <?php foreach ($services as $service): ?>
                        <option value="<?php echo $service['name']; ?>" 
                                data-price="<?php echo $service['price']; ?>"
                                data-department="<?php echo $service['department']; ?>">
                            <?php echo $service['name']; ?> - Ksh <?php echo number_format($service['price'], 2); ?>
                        </option>
                        <?php endforeach; ?>
                        <option value="custom">Custom Item</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" class="form-control" name="items[${itemCount}][department]" readonly>
                </div>
                
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" class="form-control" name="items[${itemCount}][quantity]" 
                           min="1" value="1" onchange="calculateTotal(${itemCount})" required>
                </div>
                
                <div class="form-group">
                    <label>Unit Price</label>
                    <input type="number" class="form-control" name="items[${itemCount}][unit_price]" 
                           step="0.01" min="0" onchange="calculateTotal(${itemCount})" required>
                </div>
                
                <div class="form-group">
                    <label>Total</label>
                    <input type="number" class="form-control" name="items[${itemCount}][total]" readonly>
                </div>
                
                <div class="form-group">
                    <button type="button" class="btn btn-danger" onclick="removeItemRow(this)">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(newRow);
            itemCount++;
        }
        
        function removeItemRow(button) {
            const row = button.closest('.item-row');
            row.remove();
            calculateTotals();
        }
        
        function updateItemPrice(index) {
            const select = document.querySelector(`select[name="items[${index}][description]"]`);
            const unitPriceInput = document.querySelector(`input[name="items[${index}][unit_price]"]`);
            const departmentInput = document.querySelector(`input[name="items[${index}][department]"]`);
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.value === 'custom') {
                unitPriceInput.removeAttribute('readonly');
                unitPriceInput.value = '';
                departmentInput.removeAttribute('readonly');
                departmentInput.value = '';
            } else {
                unitPriceInput.setAttribute('readonly', 'true');
                unitPriceInput.value = selectedOption.getAttribute('data-price');
                departmentInput.setAttribute('readonly', 'true');
                departmentInput.value = selectedOption.getAttribute('data-department');
                calculateTotal(index);
            }
        }
        
        function calculateTotal(index) {
            const quantity = document.querySelector(`input[name="items[${index}][quantity]"]`).value;
            const unitPrice = document.querySelector(`input[name="items[${index}][unit_price]"]`).value;
            const totalInput = document.querySelector(`input[name="items[${index}][total]"]`);
            
            const total = quantity * unitPrice;
            totalInput.value = total.toFixed(2);
            calculateTotals();
        }
        
        function calculateTotals() {
            let subtotal = 0;
            const totalInputs = document.querySelectorAll('input[name$="[total]"]');
            
            totalInputs.forEach(input => {
                subtotal += parseFloat(input.value) || 0;
            });
            
            const paidAmount = parseFloat(document.getElementById('paid_amount').value) || 0;
            const balance = subtotal - paidAmount;
            
            document.getElementById('subtotal').textContent = 'Ksh ' + subtotal.toFixed(2);
            document.getElementById('display-paid').textContent = 'Ksh ' + paidAmount.toFixed(2);
            document.getElementById('balance-due').textContent = 'Ksh ' + balance.toFixed(2);
        }
        
        // Initialize calculations
        document.addEventListener('DOMContentLoaded', function() {
            calculateTotals();
            document.getElementById('paid_amount').addEventListener('input', calculateTotals);
        });
    </script>
</body>
</html>