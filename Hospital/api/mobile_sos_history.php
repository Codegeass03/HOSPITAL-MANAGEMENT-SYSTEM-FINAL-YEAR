<?php
// api/mobile_sos_history.php
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

if (!$patient_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Patient ID required'
    ]);
    exit();
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            sa.id,
            sa.emergency_type,
            sa.priority,
            sa.status,
            sa.latitude,
            sa.longitude,
            sa.location_address,
            sa.medical_summary,
            sa.assigned_to,
            sa.response_team_id,
            sa.estimated_arrival,
            sa.actual_arrival,
            sa.created_at,
            sa.updated_at,
            sa.responded_at,
            sa.resolved_at,
            sa.notes,
            CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name
        FROM sos_alerts sa
        LEFT JOIN users u ON sa.assigned_to = u.id
        WHERE sa.patient_id = ?
        ORDER BY sa.created_at DESC
        LIMIT 50
    ");
    
    $stmt->execute([$patient_id]);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formatted_alerts = [];
    foreach ($alerts as $alert) {
        // Format location
        $location = null;
        if ($alert['location_address']) {
            $location = $alert['location_address'];
        } elseif ($alert['latitude'] && $alert['longitude']) {
            $location = number_format($alert['latitude'], 6) . ', ' . number_format($alert['longitude'], 6);
        }
        
        // Calculate response time (from created_at to responded_at)
        $response_time_minutes = null;
        if ($alert['responded_at']) {
            $created = new DateTime($alert['created_at']);
            $responded = new DateTime($alert['responded_at']);
            $diff = $created->diff($responded);
            $response_time_minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
        }
        
        // Format response time
        $response_time = null;
        if ($response_time_minutes !== null) {
            if ($response_time_minutes < 60) {
                $response_time = $response_time_minutes . ' minute' . ($response_time_minutes != 1 ? 's' : '');
            } else {
                $hours = floor($response_time_minutes / 60);
                $mins = $response_time_minutes % 60;
                $response_time = $hours . ' hour' . ($hours != 1 ? 's' : '');
                if ($mins > 0) {
                    $response_time .= ' ' . $mins . ' minute' . ($mins != 1 ? 's' : '');
                }
            }
        }
        
        // Format resolution time (from created_at to resolved_at)
        $resolution_time_minutes = null;
        if ($alert['resolved_at']) {
            $created = new DateTime($alert['created_at']);
            $resolved = new DateTime($alert['resolved_at']);
            $diff = $created->diff($resolved);
            $resolution_time_minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
        }
        
        // Format resolution time
        $resolution_time = null;
        if ($resolution_time_minutes !== null) {
            if ($resolution_time_minutes < 60) {
                $resolution_time = $resolution_time_minutes . ' minute' . ($resolution_time_minutes != 1 ? 's' : '');
            } else {
                $hours = floor($resolution_time_minutes / 60);
                $mins = $resolution_time_minutes % 60;
                $resolution_time = $hours . ' hour' . ($hours != 1 ? 's' : '');
                if ($mins > 0) {
                    $resolution_time .= ' ' . $mins . ' minute' . ($mins != 1 ? 's' : '');
                }
            }
        }
        
        // Determine status color for frontend
        $status_color = 'info';
        switch ($alert['status']) {
            case 'pending':
                $status_color = 'warning';
                break;
            case 'active':
            case 'acknowledged':
                $status_color = 'primary';
                break;
            case 'responded':
                $status_color = 'info';
                break;
            case 'resolved':
                $status_color = 'success';
                break;
            case 'cancelled':
                $status_color = 'danger';
                break;
        }
        
        $formatted_alerts[] = [
            'id' => $alert['id'],
            'emergency_type' => $alert['emergency_type'] ?? 'Medical Emergency',
            'priority' => $alert['priority'],
            'status' => $alert['status'],
            'status_color' => $status_color,
            'latitude' => $alert['latitude'],
            'longitude' => $alert['longitude'],
            'location' => $location,
            'location_address' => $alert['location_address'],
            'medical_summary' => $alert['medical_summary'],
            'assigned_to' => $alert['assigned_to'],
            'assigned_to_name' => $alert['assigned_to_name'],
            'response_team_id' => $alert['response_team_id'],
            'estimated_arrival' => $alert['estimated_arrival'],
            'actual_arrival' => $alert['actual_arrival'],
            'created_at' => $alert['created_at'],
            'formatted_date' => date('M j, Y - g:i A', strtotime($alert['created_at'])),
            'updated_at' => $alert['updated_at'],
            'responded_at' => $alert['responded_at'],
            'resolved_at' => $alert['resolved_at'],
            'notes' => $alert['notes'],
            'response_time' => $response_time,
            'response_time_minutes' => $response_time_minutes,
            'resolution_time' => $resolution_time,
            'resolution_time_minutes' => $resolution_time_minutes
        ];
    }
    
    echo json_encode([
        'success' => true,
        'alerts' => $formatted_alerts,
        'count' => count($formatted_alerts)
    ]);
    
} catch (Exception $e) {
    error_log("Error in mobile_sos_history.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>