<html>
<head>

</head>
<body>
  <a href="delete_unit.php?id=<?php echo $row['id']; ?>" 
   onclick="return confirm('Are you sure you want to delete this unit?');">
   Delete
</a>

</body>
</html>
<?php
session_start();
if(!isset($_SESSION["admin"]))
{
?>
<!-- <script type="text/javascript">
  window.location="index.php";
</script> -->
<?php
}
?>
<?php
include "admin/connection.php";
include "includes/db_connect.php";
include "includes/sidebar.php";
$id=$_GET["id"];
mysqli_query($link, "delete from unit where id=$id") or die(mysqli_error($link));
?>

<script type="text/javascript">
    window.location = "add_new_unit.php";
</script>