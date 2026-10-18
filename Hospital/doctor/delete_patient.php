<?php
include "../includes/db_connect.php";

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $sql = "DELETE FROM patients WHERE id=$id";

    if ($conn->query($sql) === TRUE) {
        header("Location: add_new_patients.php?deleted=1");
    } else {
        echo "Error deleting record: " . $conn->error;
    }
}

$conn->close();
?>