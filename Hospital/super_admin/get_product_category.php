<?php
session_start();
include "admin/connection.php";
include "includes/db_connect.php";

if(isset($_POST['businessname'])) {
    $businessname = mysqli_real_escape_string($link, $_POST['businessname']);
    
    $query = "SELECT productcategory FROM suppliers WHERE businessname = '$businessname'";
    $result = mysqli_query($link, $query);
    
    if($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_array($result);
        echo $row['productcategory'];
    } else {
        echo "";
    }
}
?>