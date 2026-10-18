<?php
session_start();
ob_start();

$grand_total = 0;
?>

<?php if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
    <table class="cart-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Company</th>
                <th>Unit</th>
                <th>Size</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Total</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($_SESSION['cart'] as $index => $item): 
                $item_total = $item['price'] * $item['quantity'];
                $grand_total += $item_total;
            ?>
            <tr>
                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                <td><?php echo htmlspecialchars($item['company_name']); ?></td>
                <td><?php echo htmlspecialchars($item['unit']); ?></td>
                <td><?php echo htmlspecialchars($item['packing_size']); ?></td>
                <td>Ksh <?php echo number_format($item['price'], 2); ?></td>
                <td>
                    <div class="quantity-control">
                        <button onclick="update_quantity(<?php echo $index; ?>, -1)">-</button>
                        <input type="text" value="<?php echo $item['quantity']; ?>" 
                               id="qty_<?php echo $index; ?>" 
                               onchange="update_quantity_input(<?php echo $index; ?>, this.value)">
                        <button onclick="update_quantity(<?php echo $index; ?>, 1)">+</button>
                    </div>
                </td>
                <td>Ksh <?php echo number_format($item_total, 2); ?></td>
                <td>
                    <button class="delete-btn" onclick="remove_from_cart(<?php echo $index; ?>)">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <div class="cart-empty">
        <i class="fa fa-shopping-cart"></i>
        <p>No products added to cart yet</p>
    </div>
<?php endif; ?>

<?php 
$output = ob_get_clean();
echo $output;
?>