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

include "admin/connection.php";
include "includes/db_connect.php";
include "includes/sidebar.php";
?>

<style>
    /* Medical Blue Aesthetic */
    body, .blue-theme {
        background-color: #f4faff;
        color: #333;
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }
    

    .blue-header {
        background-color: #007bff !important;
        color: white !important;
        padding: 15px;
        text-align: center;
        border-radius: 6px 6px 0 0;
        font-weight: bold;
        letter-spacing: 1px;
    }

    .blue-card {
        background-color: #e9f3ff !important;
        border: 1px solid #bcd0f0 !important;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        padding: 20px;
        margin-top: 20px;
    }

    .blue-table {
        background-color: white !important;
        color: #333;
        border-collapse: collapse;
        width: 95%;
        margin: 0 auto;
        border: 1px solid #bcd0f0;
    }

    .blue-table th {
        background-color: #007bff !important;
        color: white;
        text-align: center;
        padding: 10px;
    }

    .blue-table td {
        padding: 8px;
        text-align: center;
        border: 1px solid #bcd0f0;
    }

    .blue-table tr:nth-child(even) {
        background-color: #f2f8ff;
    }

    .blue-table tr:hover {
        background-color: #dceeff;
    }

    .blue-btn {
        background-color: #007bff !important;
        color: white !important;
        border: none !important;
        border-radius: 6px;
        padding: 10px 25px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .blue-btn:hover {
        background-color: #0056b3 !important;
    }

    /* Grid Layout for Form */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-top: 10px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        font-weight: 600;
        margin-bottom: 6px;
        color: #004085;
    }

    .form-group input,
    .form-group textarea,
    .form-group select {
        padding: 10px;
        border-radius: 5px;
        border: 1px solid #bcd0f0;
        background-color: #ffffff;
        transition: all 0.3s ease;
    }

    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        border-color: #007bff;
        box-shadow: 0 0 4px rgba(0, 123, 255, 0.3);
        outline: none;
    }

    .alert {
        border-radius: 4px;
        text-align: center;
        padding: 10px;
        font-weight: 600;
    }

    .alert-success {
        background-color: #28a745;
        color: white;
    }

    .alert-danger {
        background-color: #dc3545;
        color: white;
    }

    .center-content {
        display: flex;
        justify-content: center;
        flex-direction: column;
        align-items: center;
    }
</style>

<div class="main-content">
    <div class="page-header">
        <a href="#" onclick="history.back(); return false;" class="back-button">
            <i class="fas fa-arrow-left"></i>
        </a>

        <div id="content" class="medical-theme">
            <div id="content-header" class="medical-header">
                <div id="breadcrumb" class="center-content">
                    <a href="supplier.php" title="Go to Home" class="tip-bottom dark-blue-theme">
                        <i class="icon-home"></i> ADD NEW SUPPLIER
                    </a>
                </div>
            </div>

            <div class="container-fluid centered-container">
                <div class="widget-box medical-card form-centered" style="width: 80%;">
                    <div class="widget-title dark-blue-header"> 
                        <span class="icon"><i class="icon-align-justify"></i></span>
                        <h5>ADD SUPPLIER DETAILS</h5>
                    </div>

                    <div class="widget-content nopadding medical-form">
                        <form name="form1" action="" method="post" class="form-horizontal">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>First Name:</label>
                                    <input type="text" name="firstname" placeholder="Enter first name" />
                                </div>

                                <div class="form-group">
                                    <label>Last Name:</label>
                                    <input type="text" name="lastname" placeholder="Enter last name" />
                                </div>

                                <div class="form-group">
                                    <label>Business Name:</label>
                                    <input type="text" name="businessname" placeholder="Enter business name" />
                                </div>

                                <div class="form-group">
                                    <label>Product Category:</label>
                                    <select name="product_category" required>
                                        <option value="">Select category</option>
                                        <option value="Medications">Medications</option>
                                        <option value="Medical Equipment">Medical Equipment</option>
                                        <option value="Medical Supplies">Medical Supplies</option>
                                        <option value="Office Supplies">Office Supplies</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Contact:</label>
                                    <input type="text" name="contact" placeholder="Enter contact number" />
                                </div>

                                <div class="form-group">
                                    <label>Address:</label>
                                    <textarea name="address" placeholder="Enter supplier address"></textarea>
                                </div>

                                <div class="form-group">
                                    <label>City:</label>
                                    <input type="text" name="city" placeholder="Enter city" />
                                </div>
                            </div>

                            <div class="center-content" style="margin-top: 20px;">
                                <button type="submit" name="submit1" class="blue-btn">Save Supplier</button>
                            </div>

                            
                            <div class="alert alert-danger" id="error" style="display:none;">
                                OOPS! Business already exists, please try another!
                            </div>
                            <div class="alert alert-success" id="success" style="display:none;">
                                New Supplier Added successfully!
                            </div>
                        </form>
                    </div>
                </div>

                <!-- SUPPLIERS TABLE -->
                <div align="center" style="margin-top: 40px;">
                    <table class="blue-table">
                        <tr>
                            <td colspan="9" class="blue-header">SUPPLIERS</td>
                        </tr>
                        <tr>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Business Name(s)</th>
                            <th>Product Category</th>
                            <th>Contact</th>
                            <th>Address</th>
                            <th>City</th>
                            <th>Edit</th>
                            <th>Delete</th>
                        </tr>

                        <?php
                        $res = mysqli_query($link, "select * from suppliers");
                        while($row=mysqli_fetch_array($res)) {
                            echo "<tr>";
                            echo "<td>".$row["firstname"]."</td>";
                            echo "<td>".$row["lastname"]."</td>";
                            echo "<td>".$row["businessname"]."</td>";
                            echo "<td>".$row["productcategory"]."</td>";
                            echo "<td>".$row["contact"]."</td>";
                            echo "<td>".$row["address"]."</td>";
                            echo "<td>".$row["city"]."</td>";
                            echo "<td><a href='edit_supplier_info.php?id=".$row["id"]."' style='color:#007bff; text-decoration:none;'>Edit</a></td>";
                            echo "<td><a href='delete_supplier.php?id=".$row["id"]."' style='color:#ff4d4d; text-decoration:none;' onclick='return confirm(\"Are you sure you want to delete this supplier information?\");'>Delete</a></td>";
                            echo "</tr>";
                        }
                        ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
if(isset($_POST["submit1"])) {
    mysqli_query($link, "insert into suppliers values(NULL,'$_POST[firstname]','$_POST[lastname]','$_POST[businessname]', '$_POST[product_category]', '$_POST[contact]','$_POST[address]','$_POST[city]')") or die(mysqli_error($link));
?>
<script type="text/javascript">
    document.getElementById("error").style.display="none";
    document.getElementById("success").style.display="block";
    setTimeout(function(){
        window.location.href = window.location.href;
    },1000);
</script>
<?php
}
?>

<?php
include "includes/footer.php";
?>
