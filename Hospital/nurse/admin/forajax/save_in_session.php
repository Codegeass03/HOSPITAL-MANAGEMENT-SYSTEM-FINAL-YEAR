<?php
session_start();
include "../../user/connection.php";
$company_name=$_GET["company_name"];
$product_name=$_GET["product_name"];
$unit=$_GET["unit"];
$packing_size=$_GET["packing_size"];
$price=$_GET["price"];
$quantity=$_GET["quantity"];
$total=$_GET["total"];


if(isset($_SESSION['cart']))
{
    $max=sizeof($_SESSION['cart']);
    $check_available=0;
    $check_available=check_duplicate_product($company_name, $product_name, $unit, $packing_size);
    $available_quantity=0;
    $check_the_quantity=0;

    if($check_available==0)
    {
        $available_quantity = check_quantity($company_name, $product_name, $unit, $packing_size, $link);
        if($available_quantity>=$quantity)
        {
            $b = array("company_name"=>$company_name, "product_name"=>$product_name, "unit"=>$unit, "packing_size"=>$packing_size, "price"=>$price, "quantity"=>$quantity);
            array_push($_SESSION['cart'],$b);
        }
        else
        {
            echo "Entered quantity is not available";
        }
    }
    else
    {
        $av_quantity=0;
        $exist_quantity=0;
        $exist_quantity= check_the_quantity($company_name, $product_name, $unit, $packing_size);
        $exist_quantity=$exist_quantity+$quantity;
        $av_quantity=check_quantity($company_name, $product_name, $unit, $packing_size, $link);
        
        if($av_quantity>=$exist_quantity)
        {
            $check_product_no_session=check_product_no_session($company_name, $product_name, $unit, $packing_size);
            $b=array("company_name"=>$company_name, "product_name"=>$product_name, "unit"=>$unit, "packing_size"=>$packing_size, "price"=>$price, "quantity"=>$exist_quantity);
            $_SESSION['cart'] [$check_product_no_session]=$b;
        }
        else
        {
            echo "Entered quantity is not available";
        }
    }
} 
else
{
$available_quantity=check_quantity($company_name, $product_name, $unit, $packing_size, $link);
if($available_quantity>=$quantity)
{
$_SESSION['cart']=array(array("company_name"=>$company_name, "product_name"=>$product_name, "unit"=>$unit, "packing_size"=>$packing_size, "price"=>$price, "quantity"=>$quantity));
}
else
{
    echo "Entered quantity is not available";
}
}

function check_quantity($product_company, $product_name, $product_unit, $packing_size, $link)
{
    $product_quantity=0;
    $res=mysqli_query($link, "select * from stock_master where product_company='$product_company' && product_name='$product_name' && product_unit='$product_unit' && packing_size='$packing_size'") or die(mysqli_error($link));

    while($row=mysqli_fetch_array($res))
    {
        $product_quantity=$row["product_quantity"];
    }
    return $product_quantity;
}


function check_duplicate_product($product_company, $product_name, $product_unit, $packing_size)
{
    $found=0;
    $max=sizeof($_SESSION['cart']);
    for($i=0; $i<$max; $i++)
    {
        if(isset($_SESSION['cart'][$i]))
        {
            $company_name_session="";
            $product_name_session="";
            $unit_session="";
            $packing_size_session="";

            foreach($_SESSION['cart'][$i] as $key => $val)
            {
                if($key=="company_name")
                {
                    $company_name_session=$val;
                }
                else if ($key=="product_name")
                {
                    $product_name_session=$val;
                }
                else if ($key=="unit")
                {
                    $unit_name_session=$val;
                }
                else if ($key=="packing_size")
                {
                    $packing_size_session=$val;
                }
            }
            if($company_name_session==$product_company && $product_name_session==$product_name && $unit_session==$product_unit && $packing_size_session==$packing_size)
            {
                $found=$found+1;
            }
        }
    }
    return $found;
}


function check_the_quantity($product_company, $product_name, $product_unit, $packing_size)
{
    $quantity_found=0;
    $quantity_session=0;
    $max=sizeof($_SESSION['cart']);
    for($i=0; $i<$max; $i++)
    {
            $company_name_session="";
            $product_name_session="";
            $unit_session="";
            $packing_size_session="";
            $quantity_session="";

        if(isset($_SESSION['cart'] [$i]))
        {
            foreach($_SESSION['cart'][$i] as $key => $val)
            {
                if($key=="company_name")
                {
                    $company_name_session=$val;
                }
                else if ($key=="product_name")
                {
                    $product_name_session=$val;
                }
                else if ($key=="unit")
                {
                    $unit_name_session=$val;
                }
                else if ($key=="packing_size")
                {
                    $packing_size_session=$val;
                }
                else if ($key=="quantity")
                {
                    $quantity_session=$val;
                }
            }
            if($company_name_session==$product_company && $product_name_session==$product_name && $unit_session==$product_unit && $packing_size_session==$packing_size)
            {
                $quantity_found=$quantity_session;
            }
        }
    }
    return $quantity_found;
}

function check_product_no_session($product_company, $product_name, $product_unit, $packing_size)
{
    $recordno=0;
    $max=sizeof($_SESSION['cart']);
    for($i=0; $i<$max; $i++)
    {
        if(isset($_SESSION['cart'] [$i]))
        {
            $company_name_session="";
            $product_name_session="";
            $unit_session="";
            $packing_size_session="";

            foreach($_SESSION['cart'] [$i] as $key => $val)
            {
                if($key=="company_name")
                {
                    $company_name_session=$val;
                }
                else if ($key=="product_name")
                {
                    $product_name_session=$val;
                }
                else if ($key=="unit")
                {
                    $unit_name_session=$val;
                }
                else if ($key=="packing_size")
                {
                    $packing_size_session=$val;
                }
            }
            if($company_name_session==$product_company && $product_name_session==$product_name && $unit_session==$product_unit && $packing_size_session==$packing_size)
            {
                $recordno=$i;
            }
        }
    }
    return $recordno;
}
?>