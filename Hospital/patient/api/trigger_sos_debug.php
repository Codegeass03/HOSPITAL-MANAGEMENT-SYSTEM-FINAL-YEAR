<?php
session_start();
require_once "../includes/db_connect.php";

echo "<pre>";
echo "=== SOS DEBUG MODE ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents('php://input');
    echo "Raw input: " . $raw_input . "\n";
    
    $data = json_decode($raw_input, true);
    echo "Parsed data:\n";
    print_r($data);
    
    if (!$data) {
        echo "ERROR: Invalid JSON data\n";
        exit;
    }
    
    $patient_id = $data['patient_id'] ?? null;
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;
    
    echo "\n=== Processing ===\n";
    echo "Patient ID: " . ($patient_id ?: 'NULL') . "\n";
    echo "Latitude: " . ($latitude ?: 'NULL') . "\n";
    echo "Longitude: " . ($longitude ?: 'NULL') . "\n";
    
    // Check database connection
    try {
        echo "\n=== Database Check ===\n";
        $test = $pdo->query("SELECT 1");
        echo "Database connection: OK\n";
        
        // Check if patients table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'patients'");
        $patients_exists = $stmt->fetch();
        echo "Patients table: " . ($patients_exists ? 'EXISTS' : 'MISSING') . "\n";
        
        // Check if sos_alerts table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'sos_alerts'");
        $sos_exists = $stmt->fetch();
        echo "SOS alerts table: " . ($sos_exists ? 'EXISTS' : 'MISSING') . "\n";
        
        if ($patient_id) {
            // Check if patient exists
            $stmt = $pdo->prepare("SELECT id, patient_id, first_name, last_name FROM patients WHERE id = ?");
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch();
            
            if ($patient) {
                echo "\n=== Patient Found ===\n";
                echo "Patient ID: " . $patient['id'] . "\n";
                echo "Patient Code: " . $patient['patient_id'] . "\n";
                echo "Name: " . $patient['first_name'] . " " . $patient['last_name'] . "\n";
                
                // Try to insert into sos_alerts
                try {
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO sos_alerts 
                        (patient_id, emergency_type, priority, status, latitude, longitude, created_at)
                        VALUES (?, 'Medical Emergency', 'high', 'active', ?, ?, NOW())
                    ");
                    
                    $success = $insert_stmt->execute([
                        $patient_id,
                        $latitude,
                        $longitude
                    ]);
                    
                    if ($success) {
                        $alert_id = $pdo->lastInsertId();
                        echo "\n=== SUCCESS ===\n";
                        echo "Alert created with ID: " . $alert_id . "\n";
                        
                        // Return JSON for AJAX
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'alert_id' => $alert_id,
                            'message' => 'Emergency alert sent successfully'
                        ]);
                    } else {
                        echo "\n=== ERROR ===\n";
                        echo "Failed to insert into sos_alerts\n";
                        
                        $error = $insert_stmt->errorInfo();
                        echo "SQL Error: " . $error[2] . "\n";
                    }
                    
                } catch (PDOException $e) {
                    echo "\n=== INSERT ERROR ===\n";
                    echo "Error: " . $e->getMessage() . "\n";
                    echo "SQL State: " . $e->getCode() . "\n";
                }
                
            } else {
                echo "\n=== ERROR ===\n";
                echo "Patient not found with ID: " . $patient_id . "\n";
            }
        }
        
    } catch (PDOException $e) {
        echo "\n=== DATABASE ERROR ===\n";
        echo "Error: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "ERROR: This endpoint requires POST method\n";
}

echo "\n=== END DEBUG ===\n";
echo "</pre>";
?>