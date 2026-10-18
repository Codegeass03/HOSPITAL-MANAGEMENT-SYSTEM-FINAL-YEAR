<?php include __DIR__ . '/../partials/header.php'; ?>
<h2>Create User</h2>
<form method="POST" action="/Hospital/routes/users.php?action=store">
    <label>Username:</label><input type="text" name="username" required><br><br>
    <label>Email:</label><input type="email" name="email" required><br><br>
    <label>Password:</label><input type="password" name="password" required><br><br>
    <label>Role:</label><input type="number" name="role_id" required><br><br>
    <button type="submit">Create</button>
</form>
<?php include __DIR__ . '/../partials/footer.php'; ?>
