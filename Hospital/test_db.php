<?php
// Comprehensive Database Connection Test
echo "<h2>Database Connection Diagnostic</h2>";

// Test 1: Check if includes folder exists
$include_path = "super_admin/includes/db_connect.php";
echo "<h3>Test 1: File Path Check</h3>";
echo "Looking for: <strong>" . $include_path . "</strong><br>";
echo "Current directory: <strong>" . __DIR__ . "</strong><br>";
echo "Full path: <strong>" . __DIR__ . "/" . $include_path . "</strong><br>";

if (file_exists($include_path)) {
    echo "<p style='color: green;'>✓ File exists</p>";
} else {
    echo "<p style='color: red;'>✗ File NOT found!</p>";
    echo "<p>Trying alternate path...</p>";
    
    $include_path = "../includes/db_connect.php";
    if (file_exists($include_path)) {
        echo "<p style='color: green;'>✓ Found at: {$include_path}</p>";
    } else {
        die("<p style='color: red;'>✗ Cannot find db_connect.php in either location. Please check file location.</p>");
    }
}

// Test 2: Include the file
echo "<h3>Test 2: Including Database Connection</h3>";
echo "Including: {$include_path}<br>";

ob_start(); // Capture any output from the include
include $include_path;
$include_output = ob_get_clean();

if (!empty($include_output)) {
    echo "<p>Include output:</p><pre>" . htmlspecialchars($include_output) . "</pre>";
}

// Test 3: Check if $pdo variable is set
echo "<h3>Test 3: PDO Variable Check</h3>";

if (!isset($pdo)) {
    echo "<p style='color: red;'>✗ ERROR: \$pdo variable is NOT set after including db_connect.php</p>";
    echo "<p>This means the database connection failed or the file didn't execute properly.</p>";
    
    // Try to connect directly here for testing
    echo "<h3>Test 4: Direct Connection Attempt</h3>";
    try {
        $test_pdo = new PDO("mysql:host=localhost;dbname=hospital;charset=utf8mb4", "root", "", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        echo "<p style='color: green;'>✓ Direct connection works! The issue is with your db_connect.php file.</p>";
        $pdo = $test_pdo; // Use this for the rest of the tests
    } catch (PDOException $e) {
        die("<p style='color: red;'>✗ Direct connection also failed: " . htmlspecialchars($e->getMessage()) . "</p>");
    }
} else {
    echo "<p style='color: green;'>✓ \$pdo variable exists</p>";
}

// Test 4: Check connection details
echo "<h3>Test 4: Connection Details</h3>";
try {
    $dbname = $pdo->query('SELECT DATABASE()')->fetchColumn();
    echo "<p><strong>Connected to database:</strong> {$dbname}</p>";
    
    if ($dbname !== 'hospital') {
        echo "<p style='color: orange;'>⚠ WARNING: Connected to '{$dbname}' instead of 'hospital'!</p>";
    } else {
        echo "<p style='color: green;'>✓ Correctly connected to 'hospital' database</p>";
    }
    
    // Check users table
    $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
    if (empty($tables)) {
        echo "<p style='color: red;'>✗ 'users' table not found!</p>";
    } else {
        echo "<p style='color: green;'>✓ 'users' table exists</p>";
    }
    
    // Count users
    $count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "<p><strong>Current user count:</strong> {$count}</p>";
    
    // List users
    echo "<h3>Test 5: Current Users</h3>";
    $users = $pdo->query("SELECT id, username, email, first_name, last_name, created_at FROM users ORDER BY id DESC LIMIT 5")->fetchAll();
    
    if (empty($users)) {
        echo "<p>No users in database</p>";
    } else {
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
        echo "<tr style='background: #f0f0f0;'><th>ID</th><th>Username</th><th>Email</th><th>First Name</th><th>Last Name</th><th>Created</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['first_name']}</td>";
            echo "<td>{$user['last_name']}</td>";
            echo "<td>{$user['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test INSERT
    echo "<h3>Test 6: INSERT Test</h3>";
    $test_username = 'testuser_' . time();
    $test_email = 'test_' . time() . '@test.com';
    
    $stmt = $pdo->prepare("
        INSERT INTO users (
            role_id, username, email, password_hash, role, department,
            first_name, last_name, phone, specialization, schedule, status,
            last_login, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
    ");
    
    $result = $stmt->execute([
        1,
        $test_username,
        $test_email,
        password_hash('test123', PASSWORD_DEFAULT),
        'Test',
        'Test Dept',
        'Test',
        'User',
        '0700000000',
        '',
        '',
        'active'
    ]);
    
    if ($result) {
        $new_id = $pdo->lastInsertId();
        echo "<p style='color: green;'>✓ INSERT successful! New ID: {$new_id}</p>";
        
        // Verify
        $verify = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ?");
        $verify->execute([$new_id]);
        $new_user = $verify->fetch();
        
        if ($new_user) {
            echo "<p style='color: green;'>✓ Verified in database: {$new_user['username']} ({$new_user['email']})</p>";
            
            // Delete test record
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$new_id]);
            echo "<p>✓ Test record cleaned up</p>";
        } else {
            echo "<p style='color: red;'>✗ Record not found after insert!</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ INSERT failed</p>";
    }
    
    echo "<h3 style='color: green;'>✓ All Tests Passed!</h3>";
    echo "<p>Your database connection is working. The issue must be in your form processing script.</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<br><br><a href='manage_users.php'>Back to Manage Users</a>";
?>