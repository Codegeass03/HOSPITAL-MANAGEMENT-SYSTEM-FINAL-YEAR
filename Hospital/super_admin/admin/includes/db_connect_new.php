<?php
$pdo = null;
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=hospital;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_AUTOCOMMIT => true,
        ]
    );
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}