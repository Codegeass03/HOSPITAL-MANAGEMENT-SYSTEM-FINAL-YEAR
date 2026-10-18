<?php
// edit_bed.php
session_start();
include "includes/db_connect.php";

$bed_id = $_GET['id'] ?? null;
$ward_id = $_GET['ward_id'] ?? null;

if (!$bed_id || !$ward_id) {
    $_SESSION['error'] = "Invalid bed or ward ID.";
    header("Location: wards.php");
    exit();
}

// Get bed data
$stmt = $pdo->prepare("SELECT * FROM beds WHERE id = ?");
$stmt->execute([$bed_id]);
$bed = $stmt->fetch();

if (!$bed) {
    $_SESSION['error'] = "Bed not found.";
    header("Location: beds.php?ward_id=" . $ward_id);
    exit();
}

// Get ward data
$stmt = $pdo->prepare("SELECT * FROM ward WHERE id = ?");
$stmt->execute([$ward_id]);
$ward = $stmt->fetch();

if (!$ward) {
    $_SESSION['error'] = "Ward not found.";
    header("Location: wards.php");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $bed_number = $_POST['bed_number'];
    $bed_type = $_POST['bed_type'];
    $price_per_day = $_POST['price_per_day'];
    $bed_status = $_POST['bed_status'];
    $description = $_POST['description'] ?? '';
    $features = $_POST['features'] ?? '';

    try {
        // Check if bed number already exists (excluding current bed)
        $stmt = $pdo->prepare("SELECT id FROM beds WHERE bed_number = ? AND ward_id = ? AND id != ?");
        $stmt->execute([$bed_number, $ward_id, $bed_id]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = "Bed number already exists in this ward.";
            header("Location: edit_bed.php?id=" . $bed_id . "&ward_id=" . $ward_id);
            exit();
        }

        // Update bed
        $stmt = $pdo->prepare("UPDATE beds SET bed_number = ?, bed_type = ?, bed_status = ?, price_per_day = ?, description = ?, features = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$bed_number, $bed_type, $bed_status, $price_per_day, $description, $features, $bed_id]);

        $_SESSION['success'] = "Bed updated successfully!";
        header("Location: beds.php?ward_id=" . $ward_id);
        exit();

    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating bed: " . $e->getMessage();
        header("Location: edit_bed.php?id=" . $bed_id . "&ward_id=" . $ward_id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bed - Hospital Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #334155;
        }

        .container {
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 260px;
            background: #1e293b;
            color: white;
            height: 100vh;
            position: fixed;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 2rem;
        }

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

        .btn-primary {
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

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #6b7280;
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

        .btn-secondary:hover {
            background: #4b5563;
        }

        /* Form Styles */
        .form-container {
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-width: 800px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-input, .form-select, .form-textarea {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: border-color 0.2s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
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
    </style>
</head>
<body>
    <div class="container">
        <?php include "includes/sidebar.php"; ?>
        
        <div class="main-content">
            <?php
            $success_message = isset($_SESSION['success']) ? $_SESSION['success'] : null;
            $error_message = isset($_SESSION['error']) ? $_SESSION['error'] : null;
            unset($_SESSION['success'], $_SESSION['error']);
            ?>

            <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <div class="page-header">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-edit"></i>
                        Edit Bed
                    </h1>
                    <p class="page-subtitle">
                        Update bed information for <?php echo htmlspecialchars($ward['ward_name']); ?>
                    </p>
                </div>
                <a href="beds.php?ward_id=<?php echo $ward_id; ?>" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Beds
                </a>
            </div>

            <div class="form-container">
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Bed Number *</label>
                            <input type="text" name="bed_number" class="form-input" 
                                   value="<?php echo htmlspecialchars($bed['bed_number']); ?>" 
                                   placeholder="e.g., BED-001" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Bed Type *</label>
                            <select name="bed_type" class="form-select" required>
                                <option value="">Select type</option>
                                <option value="Standard" <?php echo $bed['bed_type'] == 'Standard' ? 'selected' : ''; ?>>Standard</option>
                                <option value="ICU" <?php echo $bed['bed_type'] == 'ICU' ? 'selected' : ''; ?>>ICU</option>
                                <option value="Ventilator" <?php echo $bed['bed_type'] == 'Ventilator' ? 'selected' : ''; ?>>Ventilator</option>
                                <option value="Oxygen Support" <?php echo $bed['bed_type'] == 'Oxygen Support' ? 'selected' : ''; ?>>Oxygen Support</option>
                                <option value="Isolation" <?php echo $bed['bed_type'] == 'Isolation' ? 'selected' : ''; ?>>Isolation</option>
                                <option value="Electric" <?php echo $bed['bed_type'] == 'Electric' ? 'selected' : ''; ?>>Electric</option>
                                <option value="Manual" <?php echo $bed['bed_type'] == 'Manual' ? 'selected' : ''; ?>>Manual</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Price per Day *</label>
                            <input type="number" name="price_per_day" class="form-input" 
                                   value="<?php echo htmlspecialchars($bed['price_per_day']); ?>" 
                                   placeholder="e.g., 5000" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="bed_status" class="form-select">
                                <option value="available" <?php echo $bed['bed_status'] == 'available' ? 'selected' : ''; ?>>Available</option>
                                <option value="occupied" <?php echo $bed['bed_status'] == 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                                <option value="reserved" <?php echo $bed['bed_status'] == 'reserved' ? 'selected' : ''; ?>>Reserved</option>
                                <option value="maintenance" <?php echo $bed['bed_status'] == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                <option value="cleaning" <?php echo $bed['bed_status'] == 'cleaning' ? 'selected' : ''; ?>>Cleaning</option>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-textarea" placeholder="Enter bed description"><?php echo htmlspecialchars($bed['description']); ?></textarea>
                        </div>
                        <div class="form-group full-width">
                            <label class="form-label">Features</label>
                            <textarea name="features" class="form-textarea" placeholder="Enter features (comma separated)"><?php echo htmlspecialchars($bed['features']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="beds.php?ward_id=<?php echo $ward_id; ?>" class="btn-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i>
                            Update Bed
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>