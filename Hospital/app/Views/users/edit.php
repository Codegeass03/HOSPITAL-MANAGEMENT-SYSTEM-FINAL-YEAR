<?php include __DIR__ . '/../partials/header.php'; ?>
<h2>Edit User</h2>
<form method="POST" action="/Hospital/routes/users.php?action=update&id=<?= $user['id'] ?>">
    <label>Username:</label><input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" required><br><br>
    <label>Email:</label><input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required><br><br>
    <label>Role:</label><input type="number" name="role_id" value="<?= htmlspecialchars($user['role_id']) ?>" required><br><br>
    <button type="submit">Update</button>
</form>
<?php include __DIR__ . '/../partials/footer.php'; ?>
