<?php include 'includes/topbar.php'; ?>
<?php include 'includes/sidebar.php'; ?>
<?php include 'includes/db_connect.php'; ?>

<div class="main-content">
  <h1>User Management</h1>

  <!-- Add User Button -->
  <button onclick="window.location.href='user/add_user.php'" class="btn btn-primary">
    + Add New User
  </button>

  <!-- User Table -->
  <table class="table">
    <thead>
      <tr>
        <th>ID</th>
        <th>Full Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Department</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $sql = "SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.email, r.role_name, d.name AS department, u.status 
              FROM users u
              LEFT JOIN roles r ON u.role_id = r.id
              LEFT JOIN departments d ON u.department_id = d.id";
      $result = $conn->query($sql);
      if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
          echo "<tr>
                  <td>{$row['id']}</td>
                  <td>{$row['name']}</td>
                  <td>{$row['email']}</td>
                  <td>{$row['role_name']}</td>
                  <td>{$row['department']}</td>
                  <td>" . ($row['status'] == 1 ? "Active" : "Inactive") . "</td>
                  <td>
                    <a href='user/edit_user.php?id={$row['id']}' class='btn btn-sm btn-warning'>Edit</a>
                    <a href='user/deactivate_user.php?id={$row['id']}' class='btn btn-sm btn-danger'>Deactivate</a>
                  </td>
                </tr>";
        }
      } else {
        echo "<tr><td colspan='7'>No users found.</td></tr>";
      }
      ?>
    </tbody>
  </table>
</div>
