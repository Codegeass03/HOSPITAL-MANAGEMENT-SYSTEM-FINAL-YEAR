<?php
// Database connection configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "medicare";

// Create connection
$database = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($database->connect_error) {
    die("Connection failed: " . $database->connect_error);
}

// Set charset to utf8
$database->set_charset("utf8");

// Debug: Uncomment the line below to test if connection is working
// echo "Database connected successfully!";
?>