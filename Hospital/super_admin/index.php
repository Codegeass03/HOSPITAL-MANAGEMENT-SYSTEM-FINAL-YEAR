<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediCare HMS - Role Gateway</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .role-container {
            max-width: 800px;
            background: #ffffff;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .role-btn {
            width: 100%;
            margin-bottom: 0.8rem;
            font-size: 1.1rem;
            padding: 0.75rem;
            border-radius: 0.6rem;
            cursor: pointer;
        }
        .title {
            font-weight: 700;
            text-align: center;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="role-container">
        <h2 class="title">Welcome to MediCare HMS</h2>
        <p class="text-center mb-4">Select your role to continue:</p>
        
        <!-- Role Buttons -->
        <div class="d-grid gap-2">
            <button class="btn btn-dark role-btn" onclick="window.location='login.php?role=super-admin';">Super Admin</button>
            <button class="btn btn-secondary role-btn" onclick="window.location='login.php?role=admin';">Admin</button>
            <button class="btn btn-primary role-btn" onclick="window.location='login.php?role=doctor';">Doctor</button>
            <button class="btn btn-success role-btn" onclick="window.location='login.php?role=nurse';">Nurse</button>
            <button class="btn btn-info role-btn" onclick="window.location='login.php?role=pharmacist';">Pharmacist</button>
            <button class="btn btn-warning role-btn text-dark" onclick="window.location='login.php?role=lab';">Lab Technician</button>
            <button class="btn btn-danger role-btn" onclick="window.location='login.php?role=accountant';">Accountant</button>
            <button class="btn btn-outline-primary role-btn" onclick="window.location='login.php?role=receptionist';">Receptionist</button>
            <button class="btn btn-outline-success role-btn" onclick="window.location='login.php?role=patient';">Patient</button>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// index.php - Acts as a gateway to all role-based login pages
if (isset($_GET['message']) && $_GET['message'] === 'loggedout'): ?>
    <div id="logout-alert" style="
        background-color: #d4edda;
        color: #155724;
        padding: 15px 20px;
        text-align: center;
        border: 1px solid #c3e6cb;
        border-radius: 6px;
        font-weight: bold;
        position: fixed;
        top: 10px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        width: fit-content;
        max-width: 90%;
        box-shadow: 0px 4px 6px rgba(0,0,0,0.1);
    ">
        ✅ You have successfully logged out.
    </div>

    <script>
        // Auto-hide after 3 seconds with smooth fade-out
        setTimeout(() => {
            const alertBox = document.getElementById('logout-alert');
            if (alertBox) {
                alertBox.style.transition = "opacity 0.5s ease";
                alertBox.style.opacity = "0";
                setTimeout(() => alertBox.remove(), 500);
            }
        }, 3000);
    </script>
<?php endif; 
?>
<?php if (isset($_GET['message']) && $_GET['message'] === 'sessionexpired'): ?>
    <div id="logout-alert" style="
        background-color: #fff3cd;
        color: #856404;
        padding: 15px 20px;
        text-align: center;
        border: 1px solid #ffeeba;
        border-radius: 6px;
        font-weight: bold;
        position: fixed;
        top: 10px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        width: fit-content;
        max-width: 90%;
        box-shadow: 0px 4px 6px rgba(0,0,0,0.1);
    ">
        ⚠️ Your session expired due to inactivity. Please log in again.
    </div>

    <script>
        setTimeout(() => {
            const alertBox = document.getElementById('logout-alert');
            if (alertBox) {
                alertBox.style.transition = "opacity 0.5s ease";
                alertBox.style.opacity = "0";
                setTimeout(() => alertBox.remove(), 500);
            }
        }, 5000);
    </script>
<?php endif; 
?>



