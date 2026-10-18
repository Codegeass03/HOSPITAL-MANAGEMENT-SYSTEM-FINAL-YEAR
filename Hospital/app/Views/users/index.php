<?php include __DIR__ . '/../partials/header.php'; ?>
<h2>User Management</h2>
<a href="/Hospital/routes/users.php?action=create" class="btn btn-primary">+ Add User</a>

<table border="1" cellpadding="10">
    <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Email</th>
        <th>Role</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?= htmlspecialchars($user['id']) ?></td>
            <td><?= htmlspecialchars($user['username']) ?></td>
            <td><?= htmlspecialchars($user['email']) ?></td>
            <td><?= htmlspecialchars($user['role']) ?></td>
            <td>
                <a href="/Hospital/routes/users.php?action=edit&id=<?= $user['id'] ?>">Edit</a> |
                <a href="/Hospital/routes/users.php?action=delete&id=<?= $user['id'] ?>" 
                   onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
            </td>
        </tr>
    <?php endforeach; ?>
</table>

<?php include __DIR__ . '/../partials/footer.php'; ?>
