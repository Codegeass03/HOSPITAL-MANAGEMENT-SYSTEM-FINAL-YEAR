<?php include '../includes/db_connect.php'; ?>
<?php include '../includes/topbar.php'; ?>
<?php include '../includes/sidebar.php'; ?>

<div class="main-content">
  <h2>Add New User</h2>
  <form action="" method="POST">
    <label>First Name</label>
    <input type="text" name="first_name" required>

    <label>Last Name</label>
    <input type="text" name="last_name" required>

    <label>Email</label>
    <input type="email" name="email" required>

    <label>Password</label>
    <input type="password" name="password" required>

    <label>Role</label>
    <select name="role_id" required>
      <?php
      $roles = $conn->query("SELECT id, role_name FROM roles");
      while ($role = $roles->fetch_assoc()) {
        echo "<option value='{$role['id']}'>{$role['role_name']}</option>";
      }
      ?>
    </select>

    <label>Department</label>
    <select name="department_id">
      <?php
      $depts = $conn->query("SELECT id, name FROM departments");
      while ($dept = $depts->fetch_assoc()) {
        echo "<option value='{$dept['id']}'>{$dept['name']}</option>";
      }
      ?>
    </select>

    <button type="submit" name="submit">Create User</button>
  </form>
</div>

<?php
if (isset($_POST['submit'])) {
  $fname = $_POST['first_name'];
  $lname = $_POST['last_name'];
  $email = $_POST['email'];
  $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
  $role = $_POST['role_id'];
  $dept = $_POST['department_id'];

  $sql = "INSERT INTO users (first_name, last_name, email, password, role_id, department_id, status) 
          VALUES ('$fname', '$lname', '$email', '$pass', '$role', '$dept', 1)";
  if ($conn->query($sql)) {
    echo "<script>alert('User added successfully!'); window.location='../user_management.php';</script>";
  } else {
    echo "<p>Error: " . $conn->error . "</p>";
  }
}
?>
