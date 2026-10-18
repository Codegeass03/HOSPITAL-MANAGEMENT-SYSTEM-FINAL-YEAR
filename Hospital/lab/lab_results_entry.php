<?php
session_start();
include "includes/db_connect.php";


// Check authentication
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Lab Technician' && $_SESSION['role'] !== 'Super Admin')) {
    header("Location: login.php");
    exit();
}

$order_id = $_GET['order_id'] ?? null;
if (!$order_id) {
    header("Location: lab_module.php");
    exit();
}

// Get order details with test items
$stmt = $pdo->prepare("
    SELECT 
        lo.*,
        p.patient_id,
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        p.age,
        p.gender,
        CONCAT(u.first_name, ' ', u.last_name) as doctor_name
    FROM lab_orders lo
    JOIN patients p ON lo.patient_id = p.id
    JOIN users u ON lo.ordered_by = u.id
    WHERE lo.id = ?
");

$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['error'] = "Lab order not found.";
    header("Location: lab_module.php");
    exit();
}

// Get test items for this order
$items_stmt = $pdo->prepare("
    SELECT * FROM lab_order_items 
    WHERE lab_order_id = ? 
    ORDER BY id
");
$items_stmt->execute([$order_id]);
$test_items = $items_stmt->fetchAll();

// Handle results submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $tested_at = $_POST['tested_at'];
        $tester_id = $_SESSION['user_id'];
        $tester_name = ($_SESSION['first_name'] ?? 'Unknown') . ' ' . ($_SESSION['last_name'] ?? 'User');
        
        // Update each test item with results
        foreach ($_POST['results'] as $item_id => $result_data) {
            $stmt = $pdo->prepare("
                UPDATE lab_order_items 
                SET result_value = ?,
                    result_unit = ?,
                    reference_range = ?,
                    result_notes = ?,
                    tested_by = ?,
                    tested_at = ?,
                    status = 'completed'
                WHERE id = ?
            ");
            
            $stmt->execute([
                $result_data['value'],
                $result_data['unit'],
                $result_data['reference_range'],
                $result_data['notes'],
                $tester_name,
                $tested_at,
                $item_id
            ]);
        }
        
        // Update main order status to completed
        $order_update = $pdo->prepare("
            UPDATE lab_orders 
            SET status = 'completed', 
                tested_by = ?,
                tested_at = ?,
                completed_at = NOW()
            WHERE id = ?
        ");
        
        $order_update->execute([
            $tester_id,
            $tested_at,
            $order_id
        ]);
        
        // Create notification for the doctor
        $notif_stmt = $pdo->prepare("
            INSERT INTO system_notifications 
            (user_id, title, message, type, related_entity_type, related_entity_id)
            VALUES (?, ?, ?, 'success', 'lab_order', ?)
        ");
        
        $notif_stmt->execute([
            $order['ordered_by'],
            'Lab Results Available',
            "Test results are ready for {$order['patient_name']}",
            $order_id
        ]);
        
       $pdo->commit();
header("Location: lab_module.php?success=1");
exit();

        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error saving results: " . $e->getMessage();
    }
}
include "includes/sidebar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Lab Results - AuraCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 500px;
            padding: 2rem;
            max-width: 1200px;
        }
        
        .form-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .patient-info-card {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .result-item-card {
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background: #f8fafc;
        }
        
        .result-input-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div style="display: flex; justify-content: space-between; margin-bottom: 2rem;">
            <h1><i class="fas fa-flask"></i> Enter Lab Results</h1>
            <a href="lab_module.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
        
        <?php if (isset($error)): ?>
            <div style="background: #fee2e2; color: #dc2626; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-card">
            <div class="patient-info-card">
                <h3>Patient Information</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($order['patient_name']); ?></p>
                <p><strong>Patient ID:</strong> <?php echo htmlspecialchars($order['patient_id']); ?></p>
                <p><strong>Age/Gender:</strong> <?php echo $order['age']; ?> years, <?php echo $order['gender']; ?></p>
                <p><strong>Ordering Doctor:</strong> Dr. <?php echo htmlspecialchars($order['doctor_name']); ?></p>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Testing Date & Time</label>
                    <input type="datetime-local" name="tested_at" class="form-input" 
                           value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                </div>
                
                <h3 style="margin: 2rem 0 1rem 0;">Test Results</h3>
                
                <?php foreach ($test_items as $item): ?>
                <div class="result-item-card">
                    <h4 style="margin-bottom: 1rem;"><?php echo htmlspecialchars($item['test_name']); ?></h4>
                    
                    <div class="result-input-grid">
                        <div class="form-group">
                            <label class="form-label">Result Value</label>
                            <input type="text" name="results[<?php echo $item['id']; ?>][value]" 
                                   class="form-input" placeholder="Enter value" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Unit</label>
                            <select name="results[<?php echo $item['id']; ?>][unit]" class="form-select">
                                <option value="mg/dL">mg/dL</option>
                                <option value="mmol/L">mmol/L</option>
                                <option value="μmol/L">μmol/L</option>
                                <option value="g/dL">g/dL</option>
                                <option value="/μL">/μL</option>
                                <option value="%">%</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Reference Range</label>
                            <input type="text" name="results[<?php echo $item['id']; ?>][reference_range]" 
                                   class="form-input" placeholder="e.g., 3.4-7.0 mg/dL">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Interpretation Notes</label>
                        <textarea name="results[<?php echo $item['id']; ?>][notes]" 
                                  class="form-textarea" rows="3"
                                  placeholder="Clinical interpretation and notes..."></textarea>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <a href="lab_module.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Submit Results
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>