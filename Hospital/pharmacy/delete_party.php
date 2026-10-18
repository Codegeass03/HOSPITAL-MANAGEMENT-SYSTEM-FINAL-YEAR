<?php
session_start();
if(!isset($_SESSION["admin"]))
{
?>
<script type="text/javascript">
  window.location="index.php";
</script>
<?php
}
?>
<?php
include "admin/connection.php";
$id=$_GET["id"];
mysqli_query($link, "delete from part_info where id=$id");
?>

<script type="text/javascript">
window.location = "supplier2.php";
</script>