<?php
session_start();
include "includes/db_connect.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to perform this action.";
    header("Location: login.php");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid service ID.";
    header("Location: services.php");
    exit();
}

$service_id = intval($_GET['id']);

try {
    // First, check if the service exists
    $checkStmt = $pdo->prepare("SELECT id, name FROM services WHERE id = ?");
    $checkStmt->execute([$service_id]);
    $service = $checkStmt->fetch();
    
    if (!$service) {
        $_SESSION['error'] = "Service not found.";
        header("Location: services.php");
        exit();
    }
    
    // Check if service is being used in any bills/invoices (optional safeguard)
    // Uncomment these lines if you have a billing system that references services
    /*
    $usageCheckStmt = $pdo->prepare("SELECT COUNT(*) as usage_count FROM bill_items WHERE service_id = ?");
    $usageCheckStmt->execute([$service_id]);
    $usageCount = $usageCheckStmt->fetch()['usage_count'];
    
    if ($usageCount > 0) {
        $_SESSION['error'] = "Cannot delete this service as it is being used in " . $usageCount . " billing record(s). Please deactivate it instead.";
        header("Location: services.php");
        exit();
    }
    */
    
    // Begin transaction for data integrity
    $pdo->beginTransaction();
    
    try {
        // Delete the service
        $deleteStmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
        $deleteStmt->execute([$service_id]);
        
        // Log the deletion (optional - if you have an audit log table)
        /*
        $logStmt = $pdo->prepare("
            INSERT INTO audit_log (user_id, action, table_name, record_id, details, created_at) 
            VALUES (?, 'DELETE', 'services', ?, ?, NOW())
        ");
        $logStmt->execute([
            $_SESSION['user_id'],
            $service_id,
            "Deleted service: " . $service['name']
        ]);
        */
        
        // Commit the transaction
        $pdo->commit();
        
        $_SESSION['success'] = "Service '" . htmlspecialchars($service['name']) . "' has been deleted successfully.";
        
    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: Unable to delete service. " . $e->getMessage();
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

// Redirect back to services page
header("Location: services.php");
exit();
?>