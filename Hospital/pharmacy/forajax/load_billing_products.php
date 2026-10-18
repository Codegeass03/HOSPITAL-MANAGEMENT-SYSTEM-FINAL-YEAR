<?php
session_start();
?>
<table class="table table-bordered">
    <tr>
        <th>Product Company</th>
        <th>Product Name</th>
        <th>Product Unit</th>
        <th>Product Size</th>
        <th>Product Price</th>
        <th>Product Quantity</th>
        <th>Total</th>
        <th>Delete</th>
    </tr>
<?php
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $max = sizeof($_SESSION['cart']);
    for ($i = 0; $i < $max; $i++) {
        if (isset($_SESSION['cart'][$i])) {
            $company_name_session = "";
            $product_name_session = "";
            $unit_session = "";
            $packing_size_session = "";
            $price_session = "";
            $quantity_session = "";

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
?>
<tr>
    <td><?php echo $company_name_session; ?></td>
    <td><?php echo $product_name_session; ?></td>
    <td><?php echo $unit_session; ?></td>
    <td><?php echo $packing_size_session; ?></td>
    <td><?php echo $price_session; ?></td>
    <td>
        <input 
    type="number" 
    min="1"
    value="<?php echo $quantity_session; ?>"
    onchange="update_cart_quantity(<?php echo $i; ?>, this.value)"
    style="width:70px;"
>

    </td>
    <td><?php echo ($quantity_session * $price_session); ?></td>
 <td>
    <button 
        type="button"
        style="background:#dc3545;color:#fff;border:none;padding:4px 8px;border-radius:3px;cursor:pointer"
        onclick="delete_cart_item(<?php echo $i; ?>)"
    >
        Delete
    </button>
</td>

</tr>
<?php
            }
        }
    }
}
?>
</table>

