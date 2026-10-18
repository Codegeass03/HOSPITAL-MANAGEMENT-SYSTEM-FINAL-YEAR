<?php
include "db_connect.php";
if (isset($pdo)) {
    echo "PDO connection successful!";
} else {
    echo "PDO connection failed!";
}
?>