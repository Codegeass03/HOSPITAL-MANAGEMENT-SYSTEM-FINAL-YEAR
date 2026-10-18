<?php
// api/mobile_lab_results.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

$patient_id = $_GET['patient_id'] ?? null;
$order_id = $_GET['order_id'] ?? null; // Get specific order details

if (!$patient_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Patient ID required'
    ]);
    exit();
}

try {
    if ($order_id) {
        // Get specific lab order details with results
        $stmt = $pdo->prepare("
            SELECT 
                lo.id,
                lo.order_number,
                lo.order_date,
                lo.status,
                lo.priority,
                lo.clinical_notes,
                CONCAT(d.first_name, ' ', d.last_name) as ordered_by_doctor,
                d.specialization
            FROM lab_orders lo
            LEFT JOIN users d ON lo.ordered_by = d.id
            WHERE lo.id = ? AND lo.patient_id = ?
        ");
        $stmt->execute([$order_id, $patient_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            echo json_encode([
                'success' => false,
                'message' => 'Lab order not found'
            ]);
            exit();
        }
        
        // Get test items for this order
        $items_stmt = $pdo->prepare("
            SELECT 
                loi.id,
                loi.test_name,
                loi.test_code,
                loi.specimen_type,
                loi.instructions,
                loi.result_value,
                loi.result_unit,
                loi.reference_range,
                loi.result_notes,
                loi.status,
                loi.specimen_condition,
                loi.collection_notes,
                loi.collected_by,
                loi.collected_at,
                loi.tested_by,
                loi.tested_at,
                loi.verified_by,
                loi.verified_at
            FROM lab_order_items loi
            WHERE loi.lab_order_id = ?
            ORDER BY loi.id
        ");
        $items_stmt->execute([$order_id]);
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format items
        $formatted_items = [];
        foreach ($items as $item) {
            // Determine if result is abnormal based on reference range
            $is_abnormal = false;
            $interpretation = 'Normal';
            
            if ($item['result_value'] && $item['reference_range']) {
                $result = floatval(preg_replace('/[^0-9.]/', '', $item['result_value']));
                $range = $item['reference_range'];
                preg_match('/(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)/', $range, $matches);
                
                if (count($matches) === 3) {
                    $min = floatval($matches[1]);
                    $max = floatval($matches[2]);
                    $is_normal = ($result >= $min && $result <= $max);
                    $is_abnormal = !$is_normal;
                    $interpretation = $is_normal ? 'Normal' : 'Abnormal';
                }
            }
            
            $formatted_items[] = [
                'id' => $item['id'],
                'test_name' => $item['test_name'],
                'test_code' => $item['test_code'],
                'specimen_type' => $item['specimen_type'],
                'instructions' => $item['instructions'],
                'result_value' => $item['result_value'],
                'result_unit' => $item['result_unit'],
                'reference_range' => $item['reference_range'],
                'result_notes' => $item['result_notes'],
                'status' => $item['status'],
                'specimen_condition' => $item['specimen_condition'],
                'collection_notes' => $item['collection_notes'],
                'collected_by' => $item['collected_by'],
                'collected_at' => $item['collected_at'],
                'formatted_collected_at' => $item['collected_at'] ? date('M j, Y g:i A', strtotime($item['collected_at'])) : null,
                'tested_by' => $item['tested_by'],
                'tested_at' => $item['tested_at'],
                'formatted_tested_at' => $item['tested_at'] ? date('M j, Y g:i A', strtotime($item['tested_at'])) : null,
                'verified_by' => $item['verified_by'],
                'verified_at' => $item['verified_at'],
                'formatted_verified_at' => $item['verified_at'] ? date('M j, Y g:i A', strtotime($item['verified_at'])) : null,
                'is_abnormal' => $is_abnormal,
                'interpretation' => $interpretation,
                'is_completed' => $item['status'] === 'completed'
            ];
        }
        
        $response = [
            'success' => true,
            'order' => [
                'id' => $order['id'],
                'order_number' => $order['order_number'] ?? 'LAB-' . $order['id'],
                'order_date' => $order['order_date'],
                'formatted_order_date' => date('M j, Y', strtotime($order['order_date'])),
                'status' => $order['status'],
                'priority' => $order['priority'],
                'clinical_notes' => $order['clinical_notes'],
                'ordered_by_doctor' => $order['ordered_by_doctor'],
                'specialty' => $order['specialization'],
                'test_count' => count($formatted_items),
                'completed_count' => count(array_filter($formatted_items, fn($item) => $item['is_completed']))
            ],
            'tests' => $formatted_items,
            'has_abnormal_results' => !empty(array_filter($formatted_items, fn($item) => $item['is_abnormal']))
        ];
        
    } else {
        // Get all lab orders for patient
        $stmt = $pdo->prepare("
            SELECT 
                lo.id,
                lo.order_number,
                lo.order_date,
                lo.status,
                lo.priority,
                lo.clinical_notes,
                COUNT(loi.id) as test_count,
                SUM(CASE WHEN loi.status = 'completed' THEN 1 ELSE 0 END) as completed_count,
                CONCAT(d.first_name, ' ', d.last_name) as ordered_by_doctor,
                d.specialization
            FROM lab_orders lo
            LEFT JOIN lab_order_items loi ON lo.id = loi.lab_order_id
            LEFT JOIN users d ON lo.ordered_by = d.id
            WHERE lo.patient_id = ?
            GROUP BY lo.id
            ORDER BY lo.order_date DESC
        ");
        $stmt->execute([$patient_id]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format orders
        $formatted_orders = [];
        foreach ($orders as $order) {
            $formatted_orders[] = [
                'id' => $order['id'],
                'order_number' => $order['order_number'] ?? 'LAB-' . $order['id'],
                'order_date' => $order['order_date'],
                'formatted_order_date' => date('M j, Y', strtotime($order['order_date'])),
                'status' => $order['status'],
                'priority' => $order['priority'],
                'clinical_notes' => $order['clinical_notes'],
                'test_count' => $order['test_count'],
                'completed_count' => $order['completed_count'],
                'ordered_by_doctor' => $order['ordered_by_doctor'],
                'specialty' => $order['specialization'],
                'is_completed' => $order['status'] === 'completed'
            ];
        }
        
        // Group by status
        $grouped = [
            'completed' => [],
            'pending' => [],
            'in_progress' => []
        ];
        
        foreach ($formatted_orders as $order) {
            if ($order['status'] === 'completed') {
                $grouped['completed'][] = $order;
            } elseif ($order['status'] === 'in_progress') {
                $grouped['in_progress'][] = $order;
            } else {
                $grouped['pending'][] = $order;
            }
        }
        
        $response = [
            'success' => true,
            'orders' => $formatted_orders,
            'grouped' => $grouped,
            'count' => count($formatted_orders),
            'counts' => [
                'completed' => count($grouped['completed']),
                'pending' => count($grouped['pending']),
                'in_progress' => count($grouped['in_progress'])
            ]
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching lab results'
    ]);
}