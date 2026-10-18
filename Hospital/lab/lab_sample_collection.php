<?php
session_start();

include "includes/db_connect.php";

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Lab Technician' && $_SESSION['role'] !== 'Super Admin')) {
    header("Location: login.php");
    exit();
}

$order_id = $_GET['order_id'] ?? null;
if (!$order_id) {
    header("Location: lab_module.php");
    exit();
}

// Get order details
$stmt = $pdo->prepare("
    SELECT 
        lo.*,
        p.patient_id,
        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
        p.age,
        p.gender,
        p.phone,
        CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
        GROUP_CONCAT(DISTINCT loi.test_name SEPARATOR ', ') as test_names,
        GROUP_CONCAT(DISTINCT loi.specimen_type SEPARATOR ', ') as specimen_types
    FROM lab_orders lo
    JOIN patients p ON lo.patient_id = p.id
    JOIN users u ON lo.ordered_by = u.id
    LEFT JOIN lab_order_items loi ON lo.id = loi.lab_order_id
    WHERE lo.id = ?
    GROUP BY lo.id
");

$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    $_SESSION['error'] = "Lab order not found.";
    header("Location: lab_module.php");
    exit();
}

// Get current user's name
$collector_name = ($_SESSION['first_name'] ?? 'Unknown') . ' ' . ($_SESSION['last_name'] ?? 'User');

include "includes/sidebar.php";
// Handle sample collection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $collection_time = $_POST['collection_time'];
        $specimen_condition = $_POST['specimen_condition'];
        $collection_notes = $_POST['collection_notes'];
        
        // Update lab order status to in_progress
        $update_stmt = $pdo->prepare("
            UPDATE lab_orders 
            SET status = 'in_progress', 
                collected_by = ?, 
                collected_at = ?
            WHERE id = ?
        ");
        
        $update_stmt->execute([
            $_SESSION['user_id'],
            $collection_time,
            $order_id
        ]);
        
        // Update all lab order items
        $item_stmt = $pdo->prepare("
            UPDATE lab_order_items 
            SET specimen_condition = ?,
                collection_notes = ?,
                collected_by = ?,
                collected_at = ?,
                status = 'collected'
            WHERE lab_order_id = ?
        ");
        
        $item_stmt->execute([
            $specimen_condition,
            $collection_notes,
            $collector_name,
            $collection_time,
            $order_id
        ]);
        
        // Create notification for the doctor
        $notif_stmt = $pdo->prepare("
            INSERT INTO system_notifications 
            (user_id, title, message, type, related_entity_type, related_entity_id)
            VALUES (?, 'Sample Collected', ?, 'info', 'lab_order', ?)
        ");
        
        $message = "Sample collected for {$order['patient_name']} - {$order['test_names']}";
        $notif_stmt->execute([$order['ordered_by'], $message, $order_id]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "Sample collected successfully! Order moved to testing queue.";
        // header("Location: lab_module.php");
        // exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error collecting sample: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sample Collection - AuraCare HMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 400px;
            padding: 2rem;
            max-width: 1200px;
        }
        
        .form-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }
        
        .patient-info-card {
            background: #f8fafc;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 1rem;
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
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div style="display: flex; justify-content: space-between; margin-bottom: 2rem;">
            <h1><i class="fas fa-syringe"></i> Sample Collection</h1>
            <a href="lab_module.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Lab
            </a>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-card">
            <div class="patient-info-card">
                <h3>Patient Information</h3>
                <div>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($order['patient_name']); ?></p>
                    <p><strong>Patient ID:</strong> <?php echo htmlspecialchars($order['patient_id']); ?></p>
                    <p><strong>Age/Gender:</strong> <?php echo $order['age']; ?> years, <?php echo $order['gender']; ?></p>
                    <p><strong>Tests:</strong> <?php echo htmlspecialchars($order['test_names']); ?></p>
                    <p><strong>Specimen Types:</strong> <?php echo htmlspecialchars($order['specimen_types']); ?></p>
                </div>
            </div>
            
            <form method="POST">
                <h3>Sample Collection Details</h3>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Collection Date & Time</label>
                        <input type="datetime-local" name="collection_time" class="form-input" 
                               value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Collector Name</label>
                        <input type="text" class="form-input" 
                               value="<?php echo htmlspecialchars($collector_name); ?>" readonly>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Specimen Condition</label>
                    <select name="specimen_condition" class="form-select" required>
                        <option value="">Select Condition</option>
                        <option value="good">Good - Suitable for testing</option>
                        <option value="hemolyzed">Hemolyzed</option>
                        <option value="clotted">Clotted</option>
                        <option value="insufficient">Insufficient Quantity</option>
                        <option value="contaminated">Contaminated</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Collection Notes</label>
                    <textarea name="collection_notes" class="form-textarea" rows="4"
                              placeholder="Any additional notes about the collection..."></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <a href="lab_module.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Confirm Collection
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>