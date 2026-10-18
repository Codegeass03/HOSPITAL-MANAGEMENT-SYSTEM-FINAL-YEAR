<?php
session_start();
include "includes/db_connect.php";

echo "<h2>Available Users for Foreign Key</h2>";

try {
    // Check session variables
    echo "<h3>Session Variables:</h3>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    
    // Check available users
    echo "<h3>Users in Database:</h3>";
    $stmt = $pdo->query("SELECT id, username, role, department, status FROM users ORDER BY id LIMIT 10");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Department</th><th>Status</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['role']}</td>";
        echo "<td>{$user['department']}</td>";
        echo "<td>{$user['status']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>