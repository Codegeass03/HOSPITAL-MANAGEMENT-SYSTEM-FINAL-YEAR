<?php
include "../includes/db_connect.php";

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $sql = "DELETE FROM users WHERE id=$id";

    if ($conn->query($sql) === TRUE) {
        header("Location: manage_users.php?deleted=1");
    } else {
        echo "Error deleting record: " . $conn->error;
    }
}

$conn->close();
?>
