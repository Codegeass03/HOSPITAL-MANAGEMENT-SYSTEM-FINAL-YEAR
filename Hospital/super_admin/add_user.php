<?php include "../includes/db_connect.php"; ?>
<?php include "../includes/sidebar.php"; ?>

<div class="container">
    <h2>Add New Staff</h2>
    <form method="POST" action="process_add_user.php">
        <div class="form-group">
            <label>First Name</label>
            <input type="text" name="first_name" class="form-control" required>
        </div>

        <div class="form-group">
            <label>Last Name</label>
            <input type="text" name="last_name" class="form-control" required>
        </div>

        <div class="form-group">
            <label>Role</label>
            <select name="role" class="form-control" required>
                <option value="Doctor">Doctor</option>
                <option value="Nurse">Nurse</option>
                <option value="Accountant">Accountant</option>
                <option value="Receptionist">Receptionist</option>
            </select>
        </div>

        <div class="form-group">
            <label>Department</label>
            <input type="text" name="department" class="form-control">
        </div>

        <div class="form-group">
            <label>Phone</label>
            <input type="text" name="phone" class="form-control">
        </div>

        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" class="form-control">
        </div>

        <button type="submit" class="btn btn-primary">Add Staff</button>
    </form>
</div>

<?php include "../includes/footer.php"; ?>
