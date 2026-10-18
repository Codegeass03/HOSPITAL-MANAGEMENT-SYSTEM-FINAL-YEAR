<?php
session_start();
include "connection.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Login - Admin </title>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="css/bootstrap.min.css"/>
    <link rel="stylesheet" href="css/bootstrap-responsive.min.css"/>
    <link rel="stylesheet" href="css/matrix-login.css"/>
    <link href="font-awesome/css/font-awesome.css" rel="stylesheet"/>
    <link href='http://fonts.googleapis.com/css?family=Open+Sans:400,700,800' rel='stylesheet' type='text/css'>
    <style>

        body {
            background-image: url('images/pills.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            display: inline-flexbox;
            justify-content: center;
            align-items: center;
        }

        #loginbox {
            width: 350px;
            padding: 20px;
            background-color: rgba(200, 200, 200, 0);
            border-radius: 4px;
            box-shadow: 10px 20px 25px 15px #000;
        }

        .form-vertical {
            display: flex;
            flex-direction: column;
           
        }

        .control-group {
            width: 100%;
        }

        .control-group input {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        .form-actions {
            display: flex;
            justify-content: space-between;
        }

        .btn {
            padding: 10px 20px;
            background-color: lightseagreen;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .btn-info {
            background-color: #17a2b8;
        }

       
    </style>
</head>
<body>
<div id="loginbox">
    <form name="form1" class="form-vertical" action="" method="post">
        <div class="control-group normal_text"><h3>USER Login Page</h3></div>
        <div class="control-group">
            <div class="controls">
                <div class="main_input_box">
                    <span class="add-on bg_lg"><i class="icon-user"> </i></span><input type="text" placeholder="Username" name="username"required/>
                </div>
            </div>
        </div>
        <div class="control-group">
            <div class="controls">
                <div class="main_input_box">
                    <span class="add-on bg_ly"><i class="icon-lock"></i></span><input type="password" placeholder="Password" name="password" required/>
                </div>
            </div>
        </div>
        <div class="form-actions">
          <center>
        <input type="submit" name="submit1" value="login" class="btn btn-success">
        </center>
        </div>
    </form>
</div>

<?php
if (isset($_POST["submit1"])) {
    $username = mysqli_real_escape_string($link, $_POST["username"]);
    $password = mysqli_real_escape_string($link, $_POST["password"]);

    $count = 0;
    $res = mysqli_query($link, "SELECT * FROM user_registration WHERE username = '$username' AND password = '$password' AND role = 'admin' AND status = 'active'");
    $count = mysqli_num_rows($res);
    
    if ($count > 0) 
    {
        $_SESSION["user"] = $username;
        ?>
         <script type="text/javascript">
        window.location = "dashboard.php";
        </script>
        <?php
    } 
    else {
        ?>
        <div class="alert alert-danger">
        OOPS! Invalid username or password!
        </div>
        <?php
    }
}
?>

<script src="js/jquery.min.js"></script>
<script src="js/matrix.login.js"></script>
</body>
</html>