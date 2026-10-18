<?php
include '../../config/db_connect.php';

if (isset($_POST['submit'])) {
    $full_name = $_POST['full_name'];
    $gender = $_POST['gender'];
    $dob = $_POST['dob'];
    $phone = $_POST['phone'];

    $sql = "INSERT INTO patients (full_name, gender, dob, phone)
            VALUES ('$full_name', '$gender', '$dob', '$phone')";
    mysqli_query($conn, $sql);

    echo "<script>alert('Patient added successfully!');window.location='view_patients.php';</script>";
}
?>

<form method="POST">
    <input type="text" name="full_name" placeholder="Full Name" required><br>
    <select name="gender" required>
        <option value="">Select Gender</option>
        <option>Male</option>
        <option>Female</option>
    </select><br>
    <input type="date" name="dob" required><br>
    <input type="text" name="phone" placeholder="Phone Number" required><br>
    <button type="submit" name="submit">Add Patient</button>
</form>