<?php
// app/Models/Role.php
namespace App\Models;

use PDO;

class Role extends BaseModel
{
    protected string $table = 'roles';

    public function all(): array
    {
        $stmt = $this->db->query("SELECT id, name, description FROM roles ORDER BY id ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id, name, description FROM roles WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    public function permissions(int $roleId): array
    {
        $stmt = $this->db->prepare("
            SELECT p.code, p.description
            FROM permissions p
            JOIN role_permissions rp ON rp.permission_id = p.id
            WHERE rp.role_id = :role_id
        ");
        $stmt->execute([':role_id' => $roleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
