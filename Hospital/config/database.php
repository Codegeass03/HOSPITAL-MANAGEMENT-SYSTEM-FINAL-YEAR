<?php
function getDatabaseConnection() {
    $host = 'localhost';
    $db   = 'hospital'; // change to your actual database name
    $user = 'root';        // change if you set a MySQL user
    $pass = '';            // change if you set a MySQL password
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (\PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
