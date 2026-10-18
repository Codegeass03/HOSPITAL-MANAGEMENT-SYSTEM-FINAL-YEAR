<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/animations.css">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/login.css">
    <title>Login - Smart Clinic Management</title>
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .login-wrapper {
    width: 100%;
    max-width: 620px;        /* wider card */
    background-color: white;
    border: 1px solid rgb(235, 235, 235);
    border-radius: 5px;
    margin: 0;
    padding: 2rem 3.5rem;    /* add inner padding */
    box-shadow: 0 3px 5px 0 rgba(240, 240, 240, 0.3);
    animation: transitionIn-Y-over 0.5s;
}
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 6px;
        }
        .input-text {
            width: 100%;
            box-sizing: border-box;
        }
        .login-btn {
            width: 100%;
        }
        .form-footer {
            text-align: center;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
<?php
session_start();
$_SESSION["user"] = "";
$_SESSION["usertype"] = "";

date_default_timezone_set('Asia/Kolkata');
$_SESSION["date"] = date('Y-m-d');

include("connection.php");

$error = '';

 if($_POST) {
        $email = mysqli_real_escape_string($database, $_POST['useremail']);
        $password = mysqli_real_escape_string($database, $_POST['userpassword']);
        
        // Check if user exists in webuser table
        $result = $database->query("SELECT * FROM webuser WHERE email='$email'");
        
        if($result->num_rows == 1) {
            $user_data = $result->fetch_assoc();
            $utype = $user_data['usertype'];
            
            switch($utype) {
                case 'p':
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
                        $error = '<label class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Wrong credentials: Invalid email or password</label>';
                    }
                    break;
                    
                case 'a':
                    // Admin login
                    $checker = $database->query("SELECT * FROM admin WHERE aemail='$email' AND apassword='$password'");
                    if ($checker->num_rows == 1) {
                        $_SESSION['user'] = $email;
                        $_SESSION['usertype'] = 'a';
                        $_SESSION['username'] = 'Admin';
                        header('location: admin/index.php');
                        exit();
                    } else {
                        $error = '<label class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Wrong credentials: Invalid email or password</label>';
                    }
                    break;
                    
                case 'd':
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
                        $error = '<label class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Wrong credentials: Invalid email or password</label>';
                    }
                    break;
                    
                case 'r':
                    // Receptionist login - using user_registration table
                    $checker = $database->query("SELECT * FROM user_registration WHERE username='$email' AND password='$password' AND role='Receptionist' AND status='active'");
                    if ($checker->num_rows == 1) {
                        $recep_data = $checker->fetch_assoc();
                        $_SESSION['user'] = $email;
                        $_SESSION['usertype'] = 'r';
                        $_SESSION['receptionist_id'] = $recep_data['id'];
                        $_SESSION['username'] = $recep_data['firstname'] . ' ' . $recep_data['lastname'];
                        header('location: reception/index.php');
                        exit();
                    } else {
                        $error = '<label class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Wrong credentials: Invalid email or password</label>';
                    }
                    break;
                    
                case 'u':
                    // Other users (Pharmacist, Accountant, etc.) - using user_registration table
                    $checker = $database->query("SELECT * FROM user_registration WHERE username='$email' AND password='$password' AND status='active'");
                    if ($checker->num_rows == 1) {
                        $user_reg_data = $checker->fetch_assoc();
                        $role = strtolower($user_reg_data['role']);
                        $_SESSION['user'] = $email;
                        $_SESSION['usertype'] = 'u';
                        $_SESSION['user_id'] = $user_reg_data['id'];
                        $_SESSION['username'] = $user_reg_data['firstname'] . ' ' . $user_reg_data['lastname'];
                        $_SESSION['role'] = $role;
                        
                        // Redirect based on specific role
                        switch($role) {
                            case 'pharmacist':
                                header('location: pharmacy/index.php');
                                break;
                            default:
                                header('location: pharmacy/index.php');
                                break;
                        }
                        exit();
                    } else {
                        $error = '<label class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Wrong credentials: Invalid email or password</label>';
                    }
                    break;
                    
                default:
                    $error = '<label class="form-label" style="color:rgb(255, 62, 62);text-align:center;">Unknown user type. Please contact administrator.</label>';
                    break;
            }
            
        } else {
            $error = '<label class="form-label" style="color:rgb(255, 62, 62);text-align:center;">We can\'t find any account for this email.</label>';
        }
    }
?>

<div class="login-wrapper">
    <p class="header-text" style="text-align:center;">Smart Clinic Management</p>
    <p class="sub-text" style="text-align:center;">Login with your details to continue</p>

    <?php if ($error): ?>
        <p style="color:rgb(255,62,62); text-align:center; margin-bottom:1rem;"><?php echo $error; ?></p>
    <?php endif; ?>

    <form action="" method="POST">
        <div class="form-group">
            <label for="useremail" class="form-label">Email</label>
            <input type="email" id="useremail" name="useremail" class="input-text" placeholder="Email Address" required>
        </div>
        <div class="form-group">
            <label for="userpassword" class="form-label">Password</label>
            <input type="password" id="userpassword" name="userpassword" class="input-text" placeholder="Password" required>
        </div>
        <input type="submit" value="Login" class="login-btn btn-primary btn">
    </form>

    <div class="form-footer">
        <span class="sub-text">Don't have an account?</span>
        <a href="signup.php" class="hover-link1 non-style-link">Sign Up</a>
    </div>
</div>

</body>
</html>