<?php
session_start();
if(!isset($_SESSION["admin"]))
{
?>
<?php
}

include "admin/connection.php";
include "includes/db_connect.php";
include "includes/sidebar.php";
?>

<?php
$id = $_GET["id"];
$firstname = "";
$lastname = "";
$businessname = "";
$productcategory = "";
$contact = "";
$address = "";
$city = "";

$res = mysqli_query($link, "select * from suppliers where id=$id");
while($row = mysqli_fetch_array($res))
{
  $firstname = $row["firstname"];
  $lastname = $row["lastname"];
  $businessname = $row["businessname"];
  $productcategory = $row["productcategory"];
  $contact = $row["contact"];
  $address = $row["address"];
  $city = $row["city"];
}
?>

<style>
    /* --- MEDICAL BLUE THEME --- */
    body {
        background-color: #f4faff;
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        color: #333;
    }

    .blue-header {
        background-color: #007bff;
        color: white;
        text-align: center;
        padding: 15px;
        border-radius: 6px 6px 0 0;
        font-size: 20px;
        font-weight: 600;
    }

    .blue-card {
        background-color: #e9f3ff;
        border: 1px solid #bcd0f0;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        width: 80%;
        margin: 30px auto;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
    }

    .form-group label {
        font-weight: 600;
        color: #004085;
        margin-bottom: 6px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border-radius: 6px;
        border: 1px solid #bcd0f0;
        background-color: #ffffff;
        transition: all 0.3s ease;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        border-color: #007bff;
        box-shadow: 0 0 4px rgba(0,123,255,0.3);
        outline: none;
    }

    .blue-btn {
        background-color: #007bff;
        border: none;
        color: white;
        font-weight: 600;
        padding: 10px 25px;
        border-radius: 6px;
        transition: all 0.3s ease;
        margin-top: 15px;
    }

    .blue-btn:hover {
        background-color: #0056b3;
    }

    .alert {
        text-align: center;
        border-radius: 6px;
        padding: 10px;
        margin-top: 15px;
        font-weight: 600;
    }

    .alert-success {
        background-color: #28a745;
        color: white;
    }

    .center-content {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
    }

    .breadcrumb {
        text-align: center;
        font-weight: 600;
        color: #004085;
        margin-top: 20px;
    }
</style>

<div class="main-content">
    <div class="blue-header">
        EDIT SUPPLIER INFORMATION
    </div>

    <div class="blue-card">
        <form name="form1" action="" method="post">
            <div class="form-grid">
                <div class="form-group">
                    <label>First Name:</label>
                    <input type="text" name="firstname" value="<?php echo $firstname; ?>" placeholder="Enter first name" />
                </div>

                <div class="form-group">
                    <label>Last Name:</label>
                    <input type="text" name="lastname" value="<?php echo $lastname; ?>" placeholder="Enter last name" />
                </div>

                <div class="form-group">
                    <label>Business Name:</label>
                    <input type="text" name="businessname" value="<?php echo $businessname; ?>" placeholder="Enter business name" />
                </div>

                <div class="form-group">
                    <label>Product Category:</label>
                    <select name="product_category" required>
                        <option value="">Select category</option>
                        <option value="Medications" <?php if($productcategory=="Medications") echo "selected"; ?>>Medications</option>
                        <option value="Medical Equipment" <?php if($productcategory=="Medical Equipment") echo "selected"; ?>>Medical Equipment</option>
                        <option value="Medical Supplies" <?php if($productcategory=="Medical Supplies") echo "selected"; ?>>Medical Supplies</option>
                        <option value="Office Supplies" <?php if($productcategory=="Office Supplies") echo "selected"; ?>>Office Supplies</option>
                        <option value="Other" <?php if($productcategory=="Other") echo "selected"; ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Contact:</label>
                    <input type="text" name="contact" value="<?php echo $contact; ?>" placeholder="Enter contact number" />
                </div>

                <div class="form-group">
                    <label>Address:</label>
                    <textarea name="address" placeholder="Enter address"><?php echo $address; ?></textarea>
                </div>

                <div class="form-group">
                    <label>City:</label>
                    <input type="text" name="city" value="<?php echo $city; ?>" placeholder="Enter city" />
                </div>
            </div>

            <div class="center-content">
                <button type="submit" name="submit1" class="blue-btn">Update Supplier</button>
            </div>

            <div class="alert alert-success" id="success" style="display:none;">
                Record updated successfully!
            </div>
        </form>
    </div>
</div>

<?php
if(isset($_POST["submit1"]))
{
    mysqli_query($link, "update suppliers set firstname='$_POST[firstname]', lastname='$_POST[lastname]', businessname='$_POST[businessname]', productcategory='$_POST[product_category]', contact='$_POST[contact]', address='$_POST[address]', city='$_POST[city]' where id=$id") or die(mysqli_error($link));
    ?>
    <script type="text/javascript">
        document.getElementById("success").style.display="block";
        setTimeout(function(){
            window.location.href = "supplier2.php";
        }, 1000);
    </script>
    <?php
}
?>

<?php
include "includes/footer.php";
?>
