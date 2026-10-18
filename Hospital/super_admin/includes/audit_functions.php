<?php
// includes/audit_functions.php
function addAuditLog($pdo, $user_id, $action, $description, $ip_address = null, $user_agent = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent, timestamp) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $action, $description, $ip_address, $user_agent]);
        return true;
    } catch (PDOException $e) {
        error_log("Audit log error: " . $e->getMessage());
        return false;
    }
}