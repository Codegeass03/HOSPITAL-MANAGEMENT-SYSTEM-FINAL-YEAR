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
include "header.php";
include "../user/connection.php";
$bill_id=0;
$res=mysqli_query($link,"select * from billing_header order by id desc limit 1");
while($row=mysqli_fetch_array($res))
{
    $bill_id=$row["id"];
}
?>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <div id="content">
    <form name="form1" action="" method="post" class="form-horizontal nopadding">
        <!--breadcrumbs-->
        <div id="content-header">
            <div id="breadcrumb"><a href="index.html" class="tip-bottom"><i class="icon-home"></i>
                    Sale a products</a></div>
        </div>

        <div class="container-fluid">
                <div class="row-fluid" style="background-color: white; min-height: 100px; padding:10px;">
                    <div class="span12">
                        <div class="widget-box">
                            <div class="widget-title"><span class="icon"> <i class="icon-align-justify"></i> </span>
                                <h5>Sale a Products</h5>
                            </div>

                            <div class="widget-content nopadding">


                                <div class=" span4">
                                    <br>

                                    <div>
                                        <label>Full Name</label>
                                        <input type="text" class="span12" name="full_name" required>
                                    </div>
                                </div>

                                <div class="span3">
                                    <br>

                                    <div>
                                        <label>Bill Type</label>
                                        <select class="span12" name="bill_type_header">
                                            <option>Cash</option>
                                            <option>Mpesa</option>
                                            <option>Debit</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="span2">
                                    <br>

                                    <div>
                                        <label>Date</label>
                                        <input type="text" class="span12" name="bill_date"
                                               value="<?php echo date("Y-m-d") ?>"
                                               readonly>
                                    </div>
                                </div>

                                <div class="span2">
                                    <br>

                                    <div>
                                        <label>Bill No</label>
                                        <input type="text" class="span12" name="bill_no"
                                               value="<?php echo generate_bill_no($bill_id); ?>"
                                               readonly>
                                    </div>
                                </div>



                            </div>
                        </div>

                    </div>
                </div>

                <!-- new row-->
                <div class="row-fluid" style="background-color: white; min-height: 100px; padding:10px;">
                    <div class="span12">
                        <center><h4>Select A Product</h4></center>


                        <div class="span2">
                            <div>
                                <label>Product Company</label>
                                <select class="span11" name="company_name" id="company_name"
                                        onchange="select_company(this.value)">
                                    <option>Select</option>
                                    <?php
                                    $res = mysqli_query($link, "select * from company_name");
                                    while ($row = mysqli_fetch_array($res)) {
                                        echo "<option>";
                                        echo $row["company_name"];
                                        echo "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="span2">
                            <div>
                                <label>Product Name</label>
                                <div id="product_name_div">
                                    <select class="span11">
                                        <option>Select</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="span1">
                            <div>
                                <label>Unit</label>
                                <div id="unit_div">
                                    <select class="span11">
                                        <option>Select</option>
                                    </select>
                                </div>

                            </div>
                        </div>

                        <div class="span2">
                            <div>
                                <label>Packing Size</label>
                                <div id="packing_size_div">
                                    <select class="span11">
                                        <option>Select</option>
                                    </select>
                                </div>
                            </div>
                        </div>


                        <div class="span1">
                            <div>
                                <label>Price</label>
                                <input type="text" class="span11" name="price" id="price" readonly value="0">
                            </div>
                        </div>

                        <div class="span1">
                            <div>
                                <label>Enter Quantity</label>
                                <input type="text" class="span11" name="quantity" id="quantity" autocomplete="off" onkeyup="generate_total(this.value)">
                            </div>
                        </div>



                        <div class="span1">
                            <div>
                                <label>Total</label>
                                <input type="text" class="span11" name="total" id="total" value="0" readonly>
                            </div>
                        </div>

                        <div class="span1">
                            <div>
                                <label>&nbsp</label>
                                <input type="button" class="span11 btn btn-success" value="Add" onclick="add_session();">
                            </div>
                        </div>

                    </div>
                </div>

                <!-- end new row-->






            <div class="row-fluid" style="background-color: white; min-height: 100px; padding:10px;">
                    <center><h4>Taken Products</h4></center>


                       <div id="bill_products"></div>
                                    <h4>
                                        <div style="background-color: white; min-height: 100px; padding:10px;"><span style="">Total: kshs</span><span style="" id="totalbill">0</span></div>
                                    </h4>

                    <br><br><br><br>

                    <center>
                        <input type="submit" name="submit1" value="generate bill" class="btn btn-success">
                    </center>

                </div>
            </div>
        </div>
        </form>
    </div>


<script type="text/javascript">
    function select_company(company_name)
    {
        var xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange =function() {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200 ) {
                document.getElementById("product_name_div").innerHTML=xmlhttp.responseText;
            }
        };
        xmlhttp.open("GET", "forajax/load_product_using_company.php?company_name=" +company_name, true);
        xmlhttp.send();
    }

function select_product(product_name,company_name)
{
    var xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange =function() {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200 ) {
                document.getElementById("unit_div").innerHTML=xmlhttp.responseText;
            }
        };
        xmlhttp.open("GET", "forajax/load_unit_using_products.php?product_name="+product_name+"&company_name="+company_name, true);
        xmlhttp.send();
}

function select_unit(unit,product_name,company_name)
{
    var xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange =function() {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200 ) {
                document.getElementById("packing_size_div").innerHTML=xmlhttp.responseText;

                $('#packing_size').on('change', function()
                {
                  load_price(document.getElementById("packing_size").value);
                });
            }
        };
        xmlhttp.open("GET", "forajax/load_packingsize_using_unit.php?unit="+unit+"&product_name="+product_name+"&company_name="+company_name, true);
        xmlhttp.send();
}

function load_price(packing_size)
{
    var company_name=document.getElementById("company_name").value;
    var product_name=document.getElementById("product_name").value;
    var unit=document.getElementById("unit").value;

    var xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange =function() {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200 ) {
              
                document.getElementById("price").value=xmlhttp.responseText;

            }
        };
        xmlhttp.open("GET", "forajax/load_price.php?company_name="+company_name+"&product_name="+product_name+"&unit="+unit+"&packing_size="+packing_size, true);
        xmlhttp.send();
}

function generate_total(quantity)
{
    document.getElementById("total").value = eval(document.getElementById("price").value) * eval (document.getElementById("quantity").value);
}

function add_session()
{
    var product_company = document.getElementById("company_name").value;
    var product_name = document.getElementById("product_name").value;
    var unit = document.getElementById("unit").value;
    var packing_size = document.getElementById("packing_size").value;
    var price = document.getElementById("price").value;
    var quantity = document.getElementById("quantity").value;
    var total = document.getElementById("total").value;

    var xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange =function() {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200 ) {
              
                if(xmlhttp.responseText=="")
            {
                load_billing_products();
                alert("product added successfully");
            }
            else{
                load_billing_products();
                alert(xmlhttp.responseText);
            }
        }
    };
        xmlhttp.open("GET", "forajax/save_in_session.php?company_name="+product_company+"&product_name="+product_name+"&unit="+unit+"&packing_size="+packing_size+"&price="+price+"&quantity="+quantity+"&total="+total, true);
        xmlhttp.send();
}

function load_billing_products()
{
    var xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange =function() {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200 ) {
              
                document.getElementById("bill_products").innerHTML=xmlhttp.responseText;
                load_total_bill();

            }
        };
        xmlhttp.open("GET", "forajax/load_billing_products.php", true);
        xmlhttp.send();
}


function load_total_bill()
{
    var xmlhttp = new XMLHttpRequest();
        xmlhttp.onreadystatechange =function() {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200 ) {
              
                document.getElementById("totalbill").innerHTML=xmlhttp.responseText;

            }
        };
        xmlhttp.open("GET", "forajax/load_billing_amount.php", true);
        xmlhttp.send();
}

load_billing_products();

function updateQuantity(index, newQuantity, companyName, productName, unit, packingSize, price) {
    if (newQuantity <= 0) {
        alert("Quantity must be greater than 0.");
        return;
    }

    // AJAX request to update the quantity in the session
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "update_quantity.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            alert(xhr.responseText); // Response from the server
            location.reload(); // Refresh the table
        }
    };
    xhr.send(`index=${index}&quantity=${newQuantity}&company_name=${companyName}&product_name=${productName}&unit=${unit}&packing_size=${packingSize}&price=${price}`);
}


function deleteProduct(index) {
    // AJAX request to delete the product from the session
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "delete_product.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
            alert(xhr.responseText); // Response from the server
            location.reload(); // Refresh the table
        }
    };
    xhr.send(`index=${index}`);
}

</script>


<?php
function generate_bill_no($id)
{
    if ($id == "") {
        $id1 = 0;
    } else {
        $id1 = $id;
    }
    $id1 = $id1 + 1;

    $len = strlen($id1);

    if ($len == "1") {
        $id1 = "0000" . $id1;
    } elseif ($len == "2") {
        $id1 = "000" . $id1;
    } elseif ($len == "3") {
        $id1 = "00" . $id1;
    } elseif ($len == "4") {
        $id1 = "0" . $id1;
    }

    return $id1;
}

// Billing logic (moved outside the function)
if (isset($_POST["submit1"])) {
    $lastbillno = 0;

    // Insert into billing_header
    mysqli_query($link, "INSERT INTO billing_header VALUES(NULL, '$_POST[full_name]', '$_POST[bill_type_header]', '$_POST[bill_date]', '$_POST[bill_no]', '$_SESSION[admin]')")
        or die(mysqli_error($link));

    // Fetch last inserted bill ID
    $res = mysqli_query($link, "SELECT * FROM billing_header ORDER BY id DESC LIMIT 1") or die(mysqli_error($link));
    while ($row = mysqli_fetch_array($res)) {
        $lastbillno = $row["id"];
    }

    // Insert into billing_details
    $max = sizeof($_SESSION['cart']);
    for ($i = 0; $i < $max; $i++) {
        $company_name_session = "";
        $product_name_session = "";
        $unit_session = "";
        $packing_size_session = "";
        $quantity_session = "";
        $price_session = "";

        if (isset($_SESSION['cart'][$i])) {
            foreach ($_SESSION['cart'][$i] as $key => $val) {
                if ($key == "company_name") {
                    $company_name_session = $val;
                } elseif ($key == "product_name") {
                    $product_name_session = $val;
                } elseif ($key == "unit") {
                    $unit_session = $val;
                } elseif ($key == "packing_size") {
                    $packing_size_session = $val;
                } elseif ($key == "quantity") {
                    $quantity_session = $val;
                } elseif ($key == "price") {
                    $price_session = $val;
                }
            }

            if ($company_name_session != "") {
                mysqli_query($link, "INSERT INTO billing_details VALUES (NULL, '$lastbillno', '$company_name_session', '$product_name_session', '$unit_session', '$packing_size_session', '$price_session', '$quantity_session')")
                    or die(mysqli_error($link));
                    mysqli_query($link, "update stock_master set product_quantity=product_quantity-$quantity_session where product_company='$company_name_session' && product_name='$product_name_session' && product_unit='$unit_session'"); //this code will reduce stock in stock master after a product has
            }
        }
    }

    // Clear the cart
    unset($_SESSION['cart']);
    ?>
    <script type="text/javascript">
        alert("Bill generated successfully!");
        window.location.href = window.location.href;
    </script>
    <?php
}
?>
?>

<?php
include "footer.php";
?>