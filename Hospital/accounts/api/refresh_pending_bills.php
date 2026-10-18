<?php
// api/refresh_pending_bills.php
session_start();
require_once "../includes/db_connect.php";

header('Content-Type: application/json');

$patient_id = $_GET['patient_id'] ?? null;
$response = ['success' => false, 'needsRefresh' => false];

try {
    if ($patient_id) {
        // Check if patient still has outstanding balances
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM billing WHERE patient_id = ? AND balance_amount > 0) as billing_count,
                (SELECT COUNT(*) FROM lab_orders WHERE patient_id = ? AND payment_status IN ('unpaid', 'partial')) as lab_count,
                (SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status = 'completed' AND consultation_fee > 0 AND billed = 0) as consultation_count
        ");
        $stmt->execute([$patient_id, $patient_id, $patient_id]);
        $counts = $stmt->fetch();
        
        $response['success'] = true;
        $response['needsRefresh'] = ($counts['billing_count'] + $counts['lab_count'] + $counts['consultation_count']) == 0;
    }
} catch (PDOException $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>