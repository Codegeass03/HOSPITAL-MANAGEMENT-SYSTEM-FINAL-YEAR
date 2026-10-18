<?php
session_start();
if (!isset($_SESSION['role'])) {
    header("Location: ../index.php");
    exit();
}
echo "<h1>Welcome, " . ucfirst($_SESSION['role']) . "!</h1>";
echo "<p>This is the Super Admin dashboard.</p>";
echo '<a href="../../logout.php">Logout</a>';
// Add more super admin specific content here
// For example, links to manage users, view reports, etc.
// Example link to manage users
echo '<a href="../manage_users.php">Manage Users</a>';
// Example link to view reports
echo '<a href="../view_reports.php">View Reports</a>';
// Example link to system settings
echo '<a href="../system_settings.php">System Settings</a>';
// You can expand this dashboard with more functionalities as needed
?>