<?php
$host = 'localhost';
$dbname = 'hospital';
$username = 'root';
$password = '';


$database = new mysqli($host, $username, $password, $dbname);

if ($database->connect_error) {
    die("Connection failed: " . $database->connect_error);
}

// Set charset to UTF-8
$database->set_charset("utf8mb4");
?>