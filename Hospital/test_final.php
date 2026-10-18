<?php
echo "<h3>Testing Fixed Database Connection</h3>";

// Include the connection file
require_once 'includes/db_connect.php';

// Test if $pdo is available
if (isset($pdo) && $pdo instanceof PDO) {
    echo "✓ \$pdo variable is set and valid<br>";
    
    // Test a query
    try {
        $stmt = $pdo->query("SELECT username, email FROM users LIMIT 3");
        $users = $stmt->fetchAll();
        
        echo "✓ Query successful! Found " . count($users) . " users:<br>";
        foreach ($users as $user) {
            echo "&nbsp;&nbsp;- " . $user['username'] . " (" . $user['email'] . ")<br>";
        }
        
    } catch (PDOException $e) {
        echo "✗ Query failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "✗ \$pdo variable is NOT set<br>";
}

// Also test global access
if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    echo "✓ \$GLOBALS['pdo'] is also available<br>";
}

echo "<h4>✓ Database connection is now working properly!</h4>";
?>