<?php
session_start();
include "includes/db_connect.php";

if (isset($_GET['id'])) {
    try {
        $department_id = intval($_GET['id']);
        
        // Get department name for logging
        $stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
        $stmt->execute([$department_id]);
        $department = $stmt->fetch();
        
        if (!$department) {
            $_SESSION['error'] = 'Department not found.';
            header('Location: departments.php');
            exit;
        }
        
        // Check if there are users assigned to this department
        $user_check = $pdo->prepare("SELECT COUNT(*) as user_count FROM users WHERE department = ?");
        $user_check->execute([$department['name']]);
        $user_count = $user_check->fetch()['user_count'];
        
        if ($user_count > 0) {
            $_SESSION['error'] = 'Cannot delete department. There are ' . $user_count . ' staff members assigned to this department. Please reassign them first.';
            header('Location: departments.php');
            exit;
        }
        
        // Delete the department
        $delete_stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
        $delete_stmt->execute([$department_id]);
        
        // Log the activity
        logActivity("Department '{$department['name']}' was deleted.", $department_id, 'department');
        
        $_SESSION['success'] = 'Department deleted successfully!';
        
    } catch (PDOException $e) {
        error_log("Database error in delete_department.php: " . $e->getMessage());
        $_SESSION['error'] = 'Error deleting department: Database error.';
    } catch (Exception $e) {
        error_log("Error in delete_department.php: " . $e->getMessage());
        $_SESSION['error'] = 'Error deleting department: ' . $e->getMessage();
    }
} else {
    $_SESSION['error'] = 'No department ID specified.';
}

header('Location: departments.php');
exit;

function logActivity($activity, $resource_id = null, $resource_type = null) {
    // Same logging function as in process_add_department.php
    $log_message = date('Y-m-d H:i:s') . " - " . $activity . " - Resource ID: " . $resource_id . " - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
    file_put_contents('department_activity.log', $log_message, FILE_APPEND | LOCK_EX);
}
?>