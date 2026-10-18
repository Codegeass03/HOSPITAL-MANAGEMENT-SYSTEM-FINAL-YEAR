<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $pdo;

    public function __construct() {
        $this->pdo = getDatabaseConnection();
    }

    public function getAllUsers() {
        $stmt = $this->pdo->query("SELECT users.id, username, email, roles.name AS role 
                                   FROM users 
                                   LEFT JOIN roles ON users.role_id = roles.id");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUserById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createUser($username, $email, $password, $role_id) {
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("INSERT INTO users (username, email, password_hash, role_id, created_at) 
                                     VALUES (?, ?, ?, ?, NOW())");
        return $stmt->execute([$username, $email, $passwordHash, $role_id]);
    }

    public function updateUser($id, $username, $email, $role_id) {
        $stmt = $this->pdo->prepare("UPDATE users SET username = ?, email = ?, role_id = ? WHERE id = ?");
        return $stmt->execute([$username, $email, $role_id, $id]);
    }

    public function deleteUser($id) {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function resetPassword($id, $newPassword) {
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        return $stmt->execute([$passwordHash, $id]);
    }
}
