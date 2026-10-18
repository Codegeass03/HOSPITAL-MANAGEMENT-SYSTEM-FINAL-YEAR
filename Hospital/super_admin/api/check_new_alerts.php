<?php
require_once "../includes/db_connect.php";

header('Content-Type: application/json');

// Get last check time from session or request
$lastCheck = $_SESSION['last_alert_check'] ?? date('Y-m-d H:i:s', strtotime('-5 minutes'));

try {
    // Count new critical alerts since last check
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM sos_alerts 
        WHERE priority = 'critical' 
        AND status IN ('pending', 'active')
        AND created_at > ?
    ");
    $stmt->execute([$lastCheck]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update last check time
    $_SESSION['last_alert_check'] = date('Y-m-d H:i:s');
    
    echo json_encode([
        'success' => true,
        'newCriticalAlerts' => $result['count']
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>