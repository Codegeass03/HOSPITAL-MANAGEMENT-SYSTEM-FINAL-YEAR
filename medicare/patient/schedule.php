<?php
session_start();

if(isset($_SESSION["user"])){
    if(($_SESSION["user"])=="" or $_SESSION['usertype']!='p'){
        header("location: ../login.php");
        exit();
    }else{
        $useremail=$_SESSION["user"];
    }
}else{
    header("location: ../login.php");
    exit();
}

include("../connection.php");
$sqlmain= "select * from patient where pemail=?";
$stmt = $database->prepare($sqlmain);
$stmt->bind_param("s",$useremail);
$stmt->execute();
$result = $stmt->get_result();
$userfetch=$result->fetch_assoc();
$userid= $userfetch["pid"];
$username=$userfetch["pname"];

date_default_timezone_set('Africa/Nairobi');
$today = date('Y-m-d');

$sqlmain= "select * from schedule inner join doctor on schedule.docid=doctor.docid where schedule.scheduledate>='$today'  order by schedule.scheduledate asc";
$sqlpt1="";
$insertkey="";
$q='';
$searchtype="All";

if($_POST){
    if(!empty($_POST["search"])){
        $keyword=$_POST["search"];
        $sqlmain= "select * from schedule inner join doctor on schedule.docid=doctor.docid where schedule.scheduledate>='$today' and (doctor.docname='$keyword' or doctor.docname like '$keyword%' or doctor.docname like '%$keyword' or doctor.docname like '%$keyword%' or schedule.title='$keyword' or schedule.title like '$keyword%' or schedule.title like '%$keyword' or schedule.title like '%$keyword%' or schedule.scheduledate like '$keyword%' or schedule.scheduledate like '%$keyword' or schedule.scheduledate like '%$keyword%' or schedule.scheduledate='$keyword' )  order by schedule.scheduledate asc";
        $insertkey=$keyword;
        $searchtype="Search Result : ";
        $q='"';
    }
}

$result= $database->query($sqlmain);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/animations.css">  
    <!-- <link rel="stylesheet" href="../css/main.css">   -->
    <link rel="stylesheet" href="../css/admin.css">
        
    <title>Sessions</title>
    <style>
        :root {
            --primarycolor: #0891b2;
            --primarydark: #0e7490;
            --primarylight: #06b6d4;
            --secondarycolor: #14b8a6;
            --accentcolor: #22d3ee;
            --bgcolor: #f0fdfa;
            --textcolor: #134e4a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bgcolor);
            color: var(--textcolor);
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .menu {
            width: 280px;
            background: linear-gradient(135deg, var(--primarycolor) 0%, var(--primarydark) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .menu::-webkit-scrollbar {
            width: 6px;
        }

        .menu::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }

        .menu::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }

        .menu::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }

        .menu-container {
            width: 100%;
        }

        .profile-container {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 10px;
        }

        .profile-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .profile-subtitle {
            font-size: 15px;
            opacity: 0.8;
            color: white;
        }

        .logout-btn {
            width: 100%;
            padding: 10px;
            margin-top: 15px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .menu-row {
            margin: 5px 0;
        }

        .menu-btn {
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .menu-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        .menu-btn.menu-active {
            background: rgba(255,255,255,0.2);
            border-left: 4px solid white;
        }

        .menu-btn::before {
            content: '';
            width: 24px;
            height: 24px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            filter: brightness(0) invert(1);
        }

        .menu-icon-home::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'/%3E%3Cpolyline points='9 22 9 12 15 12 15 22'/%3E%3C/svg%3E");
        }

        .menu-icon-doctor::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2'/%3E%3Ccircle cx='8.5' cy='7' r='4'/%3E%3Cline x1='20' y1='8' x2='20' y2='14'/%3E%3Cline x1='23' y1='11' x2='17' y2='11'/%3E%3C/svg%3E");
        }

        .menu-icon-session::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Crect x='3' y='4' width='18' height='18' rx='2' ry='2'/%3E%3Cline x1='16' y1='2' x2='16' y2='6'/%3E%3Cline x1='8' y1='2' x2='8' y2='6'/%3E%3Cline x1='3' y1='10' x2='21' y2='10'/%3E%3C/svg%3E");
        }

        .menu-icon-appoinment::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3Cpolyline points='12 6 12 12 16 14'/%3E%3C/svg%3E");
        }

         .menu-icon-medical::before {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'/%3E%3Cpolyline points='14 2 14 8 20 8'/%3E%3Cpath d='M16 13H8'/%3E%3Cpath d='M16 17H8'/%3E%3Cpath d='M10 9H9H8'/%3E%3Ccircle cx='16' cy='9' r='1'/%3E%3C/svg%3E");
}
        .menu-icon-settings::before {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Ccircle cx='12' cy='12' r='3'/%3E%3Cpath d='M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z'/%3E%3C/svg%3E");
        }

        .menu-text {
            font-size: 15px;
            color: white;
            text-decoration: none;
        }

        .non-style-link-menu {
            text-decoration: none;
            color: white;
            display: block;
        }

        /* Main Content */
        .dash-body {
            flex: 1;
            margin-left: 280px;
            padding: 30px 50px;
            animation: transitionIn-Y-over 0.5s;
        }

        /* Top Navigation Bar */
        .nav-bar {
            background: white;
            padding: 20px 40px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--primarycolor);
            margin: 0;
        }

        .nav-subtitle {
            font-size: 14px;
            color: #64748b;
            margin-top: 5px;
        }

        .nav-date {
            text-align: right;
        }

        .date-label {
            font-size: 14px;
            color: #64748b;
            margin: 0;
        }

        .date-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--primarydark);
            margin: 5px 0 0 0;
        }

        /* Search Section */
        .search-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
            margin-bottom: 30px;
            border-left: 5px solid var(--primarycolor);
            animation: transitionIn-Y-bottom 0.4s;
        }

        .search-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .search-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--primarydark);
        }

        .session-count {
            background: var(--accentcolor);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .search-form {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .search-input {
            flex: 1;
            padding: 12px 20px;
            border: 2px solid rgba(20, 184, 166, 0.3);
            border-radius: 8px;
            background: #f8fafc;
            color: var(--textcolor);
            font-size: 16px;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primarycolor);
            background: white;
            box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.1);
        }

        .search-btn {
            padding: 12px 30px;
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(8, 145, 178, 0.2);
        }

        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        /* Sessions Grid */
        .sessions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .session-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
            transition: all 0.3s ease;
            border: 1px solid rgba(8, 145, 178, 0.1);
            animation: transitionIn-Y-bottom 0.5s;
        }

        .session-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(8, 145, 178, 0.2);
        }

        .session-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--primarycolor);
            margin-bottom: 10px;
        }

        .session-doctor {
            font-size: 16px;
            color: var(--textcolor);
            margin-bottom: 15px;
            font-weight: 500;
        }

        .session-details {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .session-date {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 5px;
        }

        .session-time {
            font-size: 16px;
            font-weight: 600;
            color: var(--primarydark);
        }

        .book-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--primarylight) 0%, var(--primarycolor) 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 15px;
        }

        .book-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(8, 145, 178, 0.3);
        }

        /* Back Button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(8, 145, 178, 0.1);
            color: var(--primarycolor);
            border: 1px solid rgba(8, 145, 178, 0.2);
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            margin-bottom: 20px;
        }

        .back-btn:hover {
            background: rgba(8, 145, 178, 0.2);
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(8, 145, 178, 0.1);
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
            color: rgba(8, 145, 178, 0.3);
        }

        .empty-title {
            font-size: 20px;
            color: var(--textcolor);
            margin-bottom: 10px;
        }

        .empty-subtitle {
            color: #64748b;
            margin-bottom: 25px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        .btn-primary-soft {
            background: rgba(8, 145, 178, 0.1);
            color: var(--primarycolor);
            border: 1px solid rgba(8, 145, 178, 0.2);
            padding: 10px 25px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }

        .btn-primary-soft:hover {
            background: rgba(8, 145, 178, 0.2);
            transform: translateY(-2px);
        }

        /* Quick Links */
        .quick-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }

        /* Animations */
        @keyframes transitionIn-Y-over {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes transitionIn-Y-bottom {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .menu {
                width: 240px;
            }
            .dash-body {
                margin-left: 240px;
                padding: 20px 30px;
            }
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            .menu {
                position: relative;
                width: 100%;
                height: auto;
            }
            .dash-body {
                margin-left: 0;
                padding: 20px;
            }
            .search-form {
                flex-direction: column;
            }
            .nav-bar {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .nav-date {
                text-align: center;
            }
            .sessions-grid {
                grid-template-columns: 1fr;
            }
            .quick-links {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar Menu -->
        <div class="menu">
            <table class="menu-container" border="0">
                <tr>
                    <td style="padding:10px" colspan="2">
                        <table border="0" class="profile-container">
                            <tr>
                                <td width="30%" style="padding-left:20px">
                                    <img src="../img/user.png" alt="" width="100%" style="border-radius:50%">
                                </td>
                                <td style="padding:0px;margin:0px;">
                                    <p class="profile-title"><?php echo substr($username,0,13)  ?>..</p>
                                    <p class="profile-subtitle"><?php echo substr($useremail,0,22)  ?></p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <a href="../logout.php"><input type="button" value="Log out" class="logout-btn"></a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-home">
                        <a href="index.php" class="non-style-link-menu"><p class="menu-text">Home</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-doctor">
                        <a href="doctors.php" class="non-style-link-menu"><p class="menu-text">All Doctors</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-session menu-active">
                        <a href="schedule.php" class="non-style-link-menu"><p class="menu-text">Scheduled Sessions</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-appoinment">
                        <a href="appointment.php" class="non-style-link-menu"><p class="menu-text">My Bookings</p></a>
                    </td>
                </tr>
                 <tr class="menu-row">
                    <td class="menu-btn menu-icon-medical">
                        <a href="ehr_records.php" class="non-style-link-menu"><p class="menu-text">Medical Records</p></a>
                    </td>
                </tr>
                <tr class="menu-row">
                    <td class="menu-btn menu-icon-settings">
                        <a href="settings.php" class="non-style-link-menu"><p class="menu-text">Settings</p></a>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Main Content -->
        <div class="dash-body">
            <!-- Top Navigation Bar -->
            <div class="nav-bar">
                <div>
                    <h1 class="nav-title">Scheduled Sessions</h1>
                    <p class="nav-subtitle">Book appointments with available doctors</p>
                </div>
                <div class="nav-date">
                    <p class="date-label">Today's Date</p>
                    <p class="date-value"><?php echo date('F j, Y'); ?></p>
                </div>
            </div>
            
            <!-- Search Section -->
            <div class="search-container">
                <div class="search-header">
                    <h2 class="search-title">🔍 Search Sessions</h2>
                    <span class="session-count"><?php echo $result->num_rows; ?> sessions</span>
                </div>
                <form action="" method="post" class="search-form">
                    <input type="search" name="search" class="search-input" 
                           placeholder="Search by doctor name, session title, or date (YYYY-MM-DD)" list="doctors" value="<?php echo htmlspecialchars($insertkey); ?>">
                    <?php
                        echo '<datalist id="doctors">';
                        $list11 = $database->query("select DISTINCT * from doctor;");
                        $list12 = $database->query("select DISTINCT * from schedule GROUP BY title;");
                        
                        for ($y=0;$y<$list11->num_rows;$y++){
                            $row00=$list11->fetch_assoc();
                            $d=$row00["docname"];
                            echo "<option value='$d'><br/>";
                        };
                        
                        for ($y=0;$y<$list12->num_rows;$y++){
                            $row00=$list12->fetch_assoc();
                            $d=$row00["title"];
                            echo "<option value='$d'><br/>";
                        };
                        echo '</datalist>';
                    ?>
                    <button type="submit" class="search-btn">Search</button>
                </form>
            </div>
            
            <!-- Sessions Grid -->
            <?php if($result->num_rows > 0): ?>
                <div class="sessions-grid">
                    <?php 
                    for ($x=0; $x<$result->num_rows; $x++){
                        $row=$result->fetch_assoc();
                        $scheduleid=$row["scheduleid"];
                        $title=$row["title"];
                        $docname=$row["docname"];
                        $scheduledate=$row["scheduledate"];
                        $scheduletime=$row["scheduletime"];
                        
                        echo '
                        <div class="session-card">
                            <h3 class="session-title">'.htmlspecialchars(substr($title,0,40)).'</h3>
                            <p class="session-doctor">👨‍⚕️ Dr. '.htmlspecialchars(substr($docname,0,30)).'</p>
                            <div class="session-details">
                                <p class="session-date">📅 '.htmlspecialchars($scheduledate).'</p>
                                <p class="session-time">⏰ '.htmlspecialchars(substr($scheduletime,0,5)).' (24h format)</p>
                            </div>
                            <a href="booking.php?id='.$scheduleid.'">
                                <button class="book-btn">Book Appointment</button>
                            </a>
                        </div>';
                    }
                    ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📅</div>
                    <h3 class="empty-title">No Sessions Found</h3>
                    <p class="empty-subtitle">
                        <?php echo $searchtype != "All" ? "We couldn't find any sessions matching '".htmlspecialchars($insertkey)."'. Try different keywords." : "There are no scheduled sessions available at the moment."; ?>
                    </p>
                    <?php if($searchtype != "All"): ?>
                        <a href="schedule.php" class="btn-primary-soft">Show All Sessions</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Quick Links -->
            <div class="quick-links">
                <a href="index.php" class="btn-primary-soft">← Back to Dashboard</a>
                <a href="doctors.php" class="btn-primary-soft">View All Doctors</a>
            </div>
        </div>
    </div>
</body>
</html>