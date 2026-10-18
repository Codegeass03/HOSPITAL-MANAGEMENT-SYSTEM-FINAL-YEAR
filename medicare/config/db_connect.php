<?php
$host = "localhost";
$username = "root";
$password = "";
$dbname = "medicare";

$conn = mysqli_connect($host, $username, $password, $dbname);

if($conn === false){
    die("Connection failed: " . mysqli_connect_error());
}
?>