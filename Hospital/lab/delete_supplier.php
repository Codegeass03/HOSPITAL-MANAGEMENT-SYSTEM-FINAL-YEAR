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

include "admin/connection.php";
include "includes/db_connect.php";
include "includes/sidebar.php";

$id=$_GET["id"];
mysqli_query($link, "delete from suppliers where id=$id");
?>

<script type="text/javascript">
window.location = "supplier2.php";
</script>