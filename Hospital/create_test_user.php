<?php
require_once __DIR__ . '/config/database.php';
$pdo = getDatabaseConnection();

// Choose a plain password
$plainPassword = "superadmin123";
$hash = password_hash($plainPassword, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, phone, status, created_at)
        VALUES (:role_id, :username, :email, :password_hash, :first_name, :last_name, :phone, :status, NOW())";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':role_id' => 1,
    ':username' => 'superadmin',
    ':email' => 'superadmin@example.com',
    ':password_hash' => $hash,
    ':first_name' => 'Super',
    ':last_name' => 'Admin',
    ':phone' => '0700000000',
    ':status' => 'active'
]);

echo "Super Admin user created with password: {$plainPassword}";
