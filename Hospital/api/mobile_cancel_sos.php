<?php
// api/mobile_cancel_sos.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$alert_id = $input['alert_id'] ?? null;

if (!$alert_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Alert ID required'
    ]);
    exit();
}

try {
    // Check if alert exists and is active
    $check_stmt = $pdo->prepare("
        SELECT id, status 
        FROM sos_alerts 
        WHERE id = ?
    ");
    $check_stmt->execute([$alert_id]);
    $alert = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$alert) {
        echo json_encode([
            'success' => false,
            'message' => 'Alert not found'
        ]);
        exit();
    }
    
    // Check if already cancelled or resolved
    if ($alert['status'] === 'cancelled') {
        echo json_encode([
            'success' => false,
            'message' => 'Alert is already cancelled'
        ]);
        exit();
    }
    
    if ($alert['status'] === 'resolved') {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot cancel resolved alert'
        ]);
        exit();
    }
    
    // Update alert status to cancelled
    $update_stmt = $pdo->prepare("
        UPDATE sos_alerts 
        SET status = 'cancelled',
            resolved_at = NOW()
        WHERE id = ?
    ");
    
    $update_stmt->execute([$alert_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Emergency alert cancelled successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error cancelling alert: ' . $e->getMessage()
    ]);
}
?>