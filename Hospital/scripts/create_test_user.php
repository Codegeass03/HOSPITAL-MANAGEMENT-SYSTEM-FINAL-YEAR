<?php
// scripts/create_test_user.php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = $GLOBALS['pdo'] ?? require __DIR__ . '/../config/database.php';

$username = 'admin';
$email = 'admin@example.com';
$password = 'admin123!'; // change as desired

$hash = password_hash($password, PASSWORD_DEFAULT);

// find Super Admin role id
$stmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Super Admin' LIMIT 1");
$stmt->execute();
$role = $stmt->fetch();
$roleId = $role ? (int)$role['id'] : 1;

$stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
$stmt->execute([':username' => $username, ':email' => $email]);
$exists = $stmt->fetchColumn();

if ($exists) {
    echo "User already exists. Aborting.\n";
    exit;
}

$insert = $pdo->prepare("INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, status, created_at)
VALUES (:role_id, :username, :email, :password_hash, :first_name, :last_name, 'active', NOW())");

$insert->execute([
    ':role_id' => $roleId,
    ':username' => $username,
    ':email' => $email,
    ':password_hash' => $hash,
    ':first_name' => 'System',
    ':last_name' => 'Admin',
]);

echo "User created: {$username} / {$password}\n";
