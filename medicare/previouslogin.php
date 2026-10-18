<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/animations.css">  
    <link rel="stylesheet" href="css/main.css">  
    <link rel="stylesheet" href="css/login.css">
        
    <title>Login - Smart Clinic Management</title>
</head>
<body>
    <?php
    session_start();
    $_SESSION["user"] = "";
    $_SESSION["usertype"] = "";
    
    date_default_timezone_set('Nairobi/Kenya');
    $date = date('Y-m-d');
    $_SESSION["date"] = $date;
    
    include("connection.php");

    $error = '<label for="promter" class="form-label"></label>';

    if($_POST) {
        $email = $_POST['useremail'];
        $password = $_POST['userpassword'];
        
        // Check if user exists in webuser table
        $result = $database->query("SELECT * FROM webuser WHERE email='$email'");
        
        if($result->num_rows == 1) {
            $user_data = $result->fetch_assoc();
            $utype = $user_data['usertype'];
            
            if ($utype == 'p') {
                // Patient login
                $checker = $database->query("SELECT * FROM patient WHERE pemail='$email' AND ppassword='$password'");
                if ($checker->num_rows == 1) {
                    $patient_data = $checker->fetch_assoc();
                    $_SESSION['user'] = $email;
                    $_SESSION['usertype'] = 'p';
                    $_SESSION['patient_id'] = $patient_data['pid'];
                    $_SESSION['username'] = $patient_data['pname'];
                    header('location: patient/index.php');
                    exit();
                } else {
                    $error = '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Wrong credentials: Invalid email or password</label>';
                }

            } elseif($utype == 'a') {
                // Admin login
                $checker = $database->query("SELECT * FROM admin WHERE aemail='$email' AND apassword='$password'");
                if ($checker->num_rows == 1) {
                    $_SESSION['user'] = $email;
                    $_SESSION['usertype'] = 'a';
                    $_SESSION['username'] = 'Admin';
                    header('location: admin/index.php');
                    exit();
                } else {
                    $error = '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Wrong credentials: Invalid email or password</label>';
                }

            } elseif($utype == 'd') {
                // Doctor login
                $checker = $database->query("SELECT * FROM doctor WHERE docemail='$email' AND docpassword='$password'");
                if ($checker->num_rows == 1) {
                    $doctor_data = $checker->fetch_assoc();
                    $_SESSION['user'] = $email;
                    $_SESSION['usertype'] = 'd';
                    $_SESSION['doctor_id'] = $doctor_data['docid'];
                    $_SESSION['username'] = $doctor_data['docname'];
                    header('location: doctor/index.php');
                    exit();
                } else {
                    $error = '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Wrong credentials: Invalid email or password</label>';
                }
            }
            
        } else {
            $error = '<label for="promter" class="form-label" style="color:rgb(255, 62, 62);text-align:center;">We cant found any account for this email.</label>';
        }
    }
    ?>
    
    <center>
    <div class="container">
        <table border="0" style="margin: 0;padding: 0;width: 60%;">
            <tr>
                <td>
                    <p class="header-text">Smart Clinic Management</p>
                </td>
            </tr>
        <div class="form-body">
            <tr>
                <td>
                    <p class="sub-text">Login with your details to continue</p>
                </td>
            </tr>
            <tr>
                <form action="" method="POST">
                <td class="label-td">
                    <label for="useremail" class="form-label">Email: </label>
                </td>
            </tr>
            <tr>
                <td class="label-td">
                    <input type="email" name="useremail" class="input-text" placeholder="Email Address" required>
                </td>
            </tr>
            <tr>
                <td class="label-td">
                    <label for="userpassword" class="form-label">Password: </label>
                </td>
            </tr>
            <tr>
                <td class="label-td">
                    <input type="password" name="userpassword" class="input-text" placeholder="Password" required>
                </td>
            </tr>
            <tr>
                <td><br>
                <?php echo $error ?>
                </td>
            </tr>
            <tr>
                <td>
                    <input type="submit" value="Login" class="login-btn btn-primary btn">
                </td>
            </tr>
        </div>
            <tr>
                <td>
                    <br>
                    <label for="" class="sub-text" style="font-weight: 280;">Don't have an account? </label>
                    <a href="signup.php" class="hover-link1 non-style-link">Sign Up</a>
                    <br><br><br>
                </td>
            </tr>
            </form>
        </table>
    </div>
</center>
</body>
</html>