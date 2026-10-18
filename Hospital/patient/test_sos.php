<?php
session_start();
require_once "includes/db_connect.php";

echo "<h2>Testing Database Connection and SOS System</h2>";

// Test database connection
try {
    $test = $pdo->query("SELECT 1");
    echo "<p style='color:green;'>✓ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
}

// Check if patients table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'patients'");
    $exists = $stmt->fetch();
    if ($exists) {
        echo "<p style='color:green;'>✓ 'patients' table exists</p>";
    } else {
        echo "<p style='color:red;'>✗ 'patients' table does NOT exist</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>✗ Error checking tables: " . $e->getMessage() . "</p>";
}

// Check if sos_alerts table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'sos_alerts'");
    $exists = $stmt->fetch();
    if ($exists) {
        echo "<p style='color:green;'>✓ 'sos_alerts' table exists</p>";
    } else {
        echo "<p style='color:red;'>✗ 'sos_alerts' table does NOT exist</p>";
        echo "<p><a href='create_sos_tables.php'>Create SOS Tables</a></p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>✗ Error checking tables: " . $e->getMessage() . "</p>";
}

// Test inserting into sos_alerts
try {
    // Get a test patient
    $stmt = $pdo->query("SELECT id FROM patients LIMIT 1");
    $test_patient = $stmt->fetch();
    
    if ($test_patient) {
        $test_stmt = $pdo->prepare("
            INSERT INTO sos_alerts (patient_id, emergency_type, priority, status, created_at)
            VALUES (?, 'Test Emergency', 'medium', 'active', NOW())
        ");
        $test_stmt->execute([$test_patient['id']]);
        
        echo "<p style='color:green;'>✓ Can insert into sos_alerts table</p>";
        
        // Clean up test record
        $pdo->query("DELETE FROM sos_alerts WHERE emergency_type = 'Test Emergency'");
    } else {
        echo "<p style='color:orange;'>⚠ No patients in database to test with</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>✗ Cannot insert into sos_alerts: " . $e->getMessage() . "</p>";
}

// Check actual error from trigger_sos.php
echo "<hr><h3>Testing trigger_sos.php directly:</h3>";
echo "<form action='api/trigger_sos.php' method='POST' id='testForm'>
    <input type='hidden' name='patient_id' value='" . ($test_patient['id'] ?? '1') . "'>
    <input type='hidden' name='latitude' value='-1.2921'>
    <input type='hidden' name='longitude' value='36.8219'>
    <button type='submit'>Test SOS Trigger</button>
</form>
<script>
document.getElementById('testForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    const response = await fetch(this.action, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    });
    
    const result = await response.json();
    alert(JSON.stringify(result, null, 2));
});
</script>";
?>