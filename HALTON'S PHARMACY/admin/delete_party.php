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
include "../user/connection.php";
$id=$_GET["id"];
mysqli_query($link, "delete from part_info where id=$id");
?>

<script type="text/javascript">
window.location = "add_new_party.php";
</script>