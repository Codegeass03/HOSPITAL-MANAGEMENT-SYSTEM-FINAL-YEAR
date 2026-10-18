<?php
// ═══════════════════════════════════════════════════════════
//  settings.php  –  Unified settings page for all staff roles
//  Works with the `users` table (medicare DB)
//  Roles: doctor | admin | patient | pharmacist | receptionist
// ═══════════════════════════════════════════════════════════
session_start();

// ── Auth guard ───────────────────────────────────────────────
if (empty($_SESSION['user']) || empty($_SESSION['usertype'])) {
    header('location: ../login.php'); exit();
}

$useremail = $_SESSION['user'];      // stored as email
$usertype  = strtolower(trim($_SESSION['usertype']));

$allowed_roles = ['doctor', 'admin', 'pharmacist', 'receptionist', 'patient'];
if (!in_array($usertype, $allowed_roles)) {
    header('location: ../login.php'); exit();
}

include('../connection.php');

// ── Fetch user from unified `users` table ────────────────────
$stmt = $database->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
$stmt->bind_param('ss', $useremail, $usertype);
$stmt->execute();
$userfetch = $stmt->get_result()->fetch_assoc();

if (!$userfetch) {
    header('location: ../login.php'); exit();
}

$userid   = $userfetch['user_id'];
$username = $userfetch['full_name'];

date_default_timezone_set('Africa/Nairobi');

// ═══════════════════════════════════════════════════════════
//  ROLE CONFIGURATION
//  Add or remove roles here — nothing else needs changing.
// ═══════════════════════════════════════════════════════════
$role_config = [

    'doctor' => [
        'label'       => 'Doctor',
        'edit_page'   => 'edit-user.php',
        'delete_page' => 'delete-user.php',
        'menu' => [
            ['icon'=>'dashboard',   'href'=>'index.php',       'label'=>'Dashboard'],
            ['icon'=>'appointment', 'href'=>'appointment.php', 'label'=>'My Appointments'],
            ['icon'=>'calendar',    'href'=>'schedule.php',    'label'=>'My Sessions'],
            ['icon'=>'patients',    'href'=>'patient.php',     'label'=>'My Patients'],
            ['icon'=>'settings',    'href'=>'settings.php',    'label'=>'Settings'],
        ],
    ],

    'admin' => [
        'label'       => 'Administrator',
        'edit_page'   => 'edit-user.php',
        'delete_page' => 'delete-user.php',
        'menu' => [
            ['icon'=>'dashboard',   'href'=>'index.php',    'label'=>'Dashboard'],
            ['icon'=>'patients',    'href'=>'staff.php',    'label'=>'Staff Management'],
            ['icon'=>'appointment', 'href'=>'reports.php',  'label'=>'Reports'],
            ['icon'=>'calendar',    'href'=>'audit.php',    'label'=>'Audit Logs'],
            ['icon'=>'settings',    'href'=>'settings.php', 'label'=>'Settings'],
        ],
    ],

    'pharmacist' => [
        'label'       => 'Pharmacist',
        'edit_page'   => 'edit-user.php',
        'delete_page' => 'delete-user.php',
        'menu' => [
            ['icon'=>'dashboard',   'href'=>'index.php',         'label'=>'Dashboard'],
            ['icon'=>'appointment', 'href'=>'prescriptions.php', 'label'=>'Prescriptions'],
            ['icon'=>'calendar',    'href'=>'dispensing.php',    'label'=>'Dispensing Log'],
            ['icon'=>'settings',    'href'=>'settings.php',      'label'=>'Settings'],
        ],
    ],

    'receptionist' => [
        'label'       => 'Receptionist',
        'edit_page'   => 'edit-user.php',
        'delete_page' => 'delete-user.php',
        'menu' => [
            ['icon'=>'dashboard',   'href'=>'index.php',        'label'=>'Dashboard'],
            ['icon'=>'appointment', 'href'=>'appointments.php', 'label'=>'Appointments'],
            ['icon'=>'patients',    'href'=>'patients.php',     'label'=>'Patients'],
            ['icon'=>'calendar',    'href'=>'schedule.php',     'label'=>'Sessions'],
            ['icon'=>'settings',    'href'=>'settings.php',     'label'=>'Settings'],
        ],
    ],

    'patient' => [
        'label'       => 'Patient',
        'edit_page'   => 'edit-user.php',
        'delete_page' => 'delete-user.php',
        'menu' => [
            ['icon'=>'dashboard',   'href'=>'index.php',      'label'=>'Home'],
            ['icon'=>'patients',    'href'=>'doctors.php',    'label'=>'All Doctors'],
            ['icon'=>'calendar',    'href'=>'schedule.php',   'label'=>'Scheduled Sessions'],
            ['icon'=>'appointment', 'href'=>'appointment.php','label'=>'My Bookings'],
            ['icon'=>'medical',     'href'=>'ehr_records.php','label'=>'Medical Records'],
            ['icon'=>'settings',    'href'=>'settings.php',   'label'=>'Settings'],
        ],
    ],
];

$role = $role_config[$usertype];

// ── Inline SVG icon data-URIs ────────────────────────────────
function menuIcon(string $name): string {
    $svgs = [
        'dashboard'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',
        'appointment' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
        'calendar'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        'patients'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'medical'     => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>',
        'settings'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-2.82 1.17V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 3a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6 1.65 1.65 0 0 0 10 3.09V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 2.82 1.17l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    ];
    $svg = $svgs[$name] ?? $svgs['settings'];
    return 'data:image/svg+xml,' . rawurlencode($svg);
}

$avatars = [
    'doctor'       => '👨‍⚕️',
    'admin'        => '🛡️',
    'pharmacist'   => '💊',
    'receptionist' => '📋',
    'patient'      => '🧑',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings – <?php echo htmlspecialchars($role['label']); ?> Portal · Smart Clinic</title>
    <style>
        :root {
            --primary:    #0891b2;
            --primary-dk: #0e7490;
            --primary-lt: #06b6d4;
            --secondary:  #14b8a6;
            --accent:     #22d3ee;
            --bg:         #f0fdfa;
            --text:       #134e4a;
        }

        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        /* Layout */
        .container { display:flex; min-height:100vh; }

        /* ── Sidebar ── */
        .menu {
            width: 270px;
            background: linear-gradient(160deg, var(--primary) 0%, var(--primary-dk) 100%);
            color: #fff;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 14px rgba(0,0,0,.13);
            z-index: 1000;
        }
        .menu::-webkit-scrollbar { width:5px; }
        .menu::-webkit-scrollbar-track { background:rgba(255,255,255,.08); }
        .menu::-webkit-scrollbar-thumb { background:rgba(255,255,255,.25); border-radius:4px; }

        .profile-block {
            padding: 22px 18px 18px;
            border-bottom: 1px solid rgba(255,255,255,.18);
        }
        .profile-row { display:flex; align-items:center; gap:13px; margin-bottom:8px; }
        .avatar {
            width:50px; height:50px; border-radius:50%;
            background:rgba(255,255,255,.15);
            display:flex; align-items:center; justify-content:center;
            font-size:22px; flex-shrink:0;
        }
        .profile-name  { font-size:15px; font-weight:600; line-height:1.3; }
        .profile-email { font-size:11.5px; opacity:.75; word-break:break-all; }
        .role-badge {
            display:inline-block; padding:3px 11px;
            background:rgba(255,255,255,.18); border-radius:20px;
            font-size:10.5px; letter-spacing:.6px;
            text-transform:uppercase; font-weight:600;
            margin-top:6px;
        }
        .logout-btn {
            margin-top:13px; width:100%; padding:9px;
            background:rgba(255,255,255,.15); color:#fff;
            border:1px solid rgba(255,255,255,.25); border-radius:6px;
            cursor:pointer; font-family:inherit; font-size:13px;
            transition:background .25s;
        }
        .logout-btn:hover { background:rgba(255,255,255,.3); }

        .nav-item {
            display:flex; align-items:center; gap:14px;
            padding:13px 20px; cursor:pointer;
            transition:background .2s;
            text-decoration:none; color:#fff;
        }
        .nav-item:hover   { background:rgba(255,255,255,.1); }
        .nav-item.active  { background:rgba(255,255,255,.18); border-left:4px solid #fff; padding-left:16px; }
        .nav-icon {
            width:22px; height:22px;
            background-size:contain; background-repeat:no-repeat; background-position:center;
            filter:brightness(0) invert(1); flex-shrink:0;
        }
        .nav-label { font-size:14px; }

        /* ── Main body ── */
        .dash-body {
            flex:1; margin-left:270px; padding:32px 48px;
            animation: slideDown .45s ease both;
        }

        /* ── Top bar ── */
        .top-bar {
            background:#fff; border-radius:12px;
            padding:22px 36px; box-shadow:0 2px 8px rgba(0,0,0,.07);
            margin-bottom:32px;
            display:flex; justify-content:space-between; align-items:center;
        }
        .top-bar-title    { font-size:26px; font-weight:700; color:var(--primary); margin:0; }
        .top-bar-subtitle { font-size:13px; color:#64748b; margin-top:4px; }
        .date-label { font-size:13px; color:#64748b; text-align:right; }
        .date-value { font-size:15px; font-weight:600; color:var(--primary-dk); margin-top:3px; text-align:right; }

        /* ── Cards grid ── */
        .cards-grid {
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));
            gap:24px; margin-top:10px;
        }
        .card {
            background:#fff; border-radius:14px; padding:30px 28px;
            box-shadow:0 3px 8px rgba(8,145,178,.09);
            border:1px solid rgba(8,145,178,.1);
            text-decoration:none; display:block;
            transition:transform .28s, box-shadow .28s;
            animation: slideUp .45s ease both;
        }
        .card:hover { transform:translateY(-6px); box-shadow:0 10px 22px rgba(8,145,178,.18); }
        .card:nth-child(1) { animation-delay:.06s; border-left:5px solid var(--primary); }
        .card:nth-child(2) { animation-delay:.13s; border-left:5px solid var(--secondary); }
        .card:nth-child(3) { animation-delay:.20s; border-left:5px solid #ef4444; }

        .card-icon  { font-size:38px; margin-bottom:16px; display:block; }
        .card-title { font-size:19px; font-weight:700; color:var(--primary-dk); margin-bottom:9px; }
        .card-title-danger { color:#ef4444; }
        .card-desc  { font-size:13.5px; color:#64748b; line-height:1.65; }

        /* ── Modal overlay ── */
        .overlay {
            position:fixed; inset:0; background:rgba(0,0,0,.48);
            display:none; justify-content:center; align-items:center;
            z-index:2000; padding:16px;
        }
        .modal {
            background:#fff; border-radius:14px; padding:38px 40px;
            max-width:600px; width:100%; max-height:92vh; overflow-y:auto;
            position:relative; animation: slideUp .3s ease both;
        }
        .modal h2 { color:var(--primary); font-size:22px; margin-bottom:22px; text-align:center; }
        .modal-close {
            position:absolute; top:14px; right:20px;
            font-size:26px; color:#94a3b8; text-decoration:none; line-height:1;
        }
        .modal-close:hover { color:var(--primary); }
        .modal-body { color:#64748b; line-height:1.7; }

        /* ── Details table ── */
        .dtable { width:100%; border-collapse:collapse; border-radius:8px; overflow:hidden; }
        .dtable tr { border-bottom:1px solid #e5e7eb; }
        .dtable tr:last-child { border-bottom:none; }
        .dtable .dlabel { padding:13px 18px; font-weight:600; color:#64748b; background:#f8fafc; width:32%; vertical-align:middle; }
        .dtable .dvalue { padding:13px 18px; color:var(--text); background:#fff; }

        /* ── Form ── */
        .form-section { margin-bottom:26px; padding-bottom:20px; border-bottom:2px solid #f1f5f9; }
        .form-section:last-of-type { border-bottom:none; }
        .form-section-title { font-size:16px; font-weight:700; color:var(--primary); margin-bottom:16px; }
        .form-label { display:block; margin-bottom:6px; font-weight:600; color:var(--text); font-size:13.5px; }
        .form-hint  { display:block; margin-top:-11px; margin-bottom:14px; color:#94a3b8; font-size:11.5px; }
        .form-input,
        .form-select {
            width:100%; padding:11px 14px;
            border:2px solid rgba(20,184,166,.28); border-radius:8px;
            background:#f8fafc; color:var(--text);
            font-size:14.5px; font-family:inherit;
            transition:border-color .25s, box-shadow .25s;
            margin-bottom:16px;
        }
        .form-input:focus,
        .form-select:focus {
            outline:none; border-color:var(--primary);
            background:#fff; box-shadow:0 0 0 3px rgba(8,145,178,.1);
        }
        .form-input[readonly] { background:#eef2f7; cursor:default; }

        /* ── Buttons ── */
        .btn {
            padding:11px 26px; border-radius:8px;
            font-weight:600; font-family:inherit; font-size:14.5px;
            cursor:pointer; text-decoration:none; display:inline-block;
            border:none; transition:transform .22s, box-shadow .22s;
        }
        .btn:hover { transform:translateY(-2px); }

        .btn-primary { background:linear-gradient(135deg, var(--primary-lt), var(--primary)); color:#fff; }
        .btn-primary:hover { box-shadow:0 4px 10px rgba(8,145,178,.35); }

        .btn-outline  { background:rgba(8,145,178,.1); color:var(--primary); border:1px solid rgba(8,145,178,.25); }
        .btn-outline:hover { background:rgba(8,145,178,.2); }

        .btn-danger   { background:linear-gradient(135deg,#f87171,#dc2626); color:#fff; }
        .btn-danger:hover { box-shadow:0 4px 10px rgba(239,68,68,.35); }

        .btn-actions  { display:flex; justify-content:center; gap:14px; margin-top:26px; flex-wrap:wrap; }

        /* ── Alerts ── */
        .alert-error {
            background:#fef2f2; border:1px solid #fecaca; color:#dc2626;
            padding:11px 16px; border-radius:8px; margin-bottom:18px;
            text-align:center; font-size:13.5px;
        }
        .success-wrap { text-align:center; padding:10px 0; }
        .success-icon { font-size:52px; margin-bottom:14px; display:block; }

        /* ── Animations ── */
        @keyframes slideDown {
            from { transform:translateY(-18px); opacity:0; }
            to   { transform:translateY(0); opacity:1; }
        }
        @keyframes slideUp {
            from { transform:translateY(18px); opacity:0; }
            to   { transform:translateY(0); opacity:1; }
        }

        /* ── Responsive ── */
        @media (max-width:1024px) {
            .menu { width:230px; }
            .dash-body { margin-left:230px; padding:22px 28px; }
        }
        @media (max-width:768px) {
            .container { flex-direction:column; }
            .menu { position:relative; width:100%; height:auto; }
            .dash-body { margin-left:0; padding:18px; }
            .top-bar { flex-direction:column; gap:14px; text-align:center; }
            .date-label, .date-value { text-align:center; }
            .modal { padding:26px 20px; }
            .btn-actions { flex-direction:column; align-items:center; }
            .btn { width:100%; max-width:280px; text-align:center; }
        }
    </style>
</head>
<body>
<div class="container">

<!-- ══════════════════ SIDEBAR ══════════════════ -->
<nav class="menu">
    <div class="profile-block">
        <div class="profile-row">
            <div class="avatar"><?php echo $avatars[$usertype] ?? '👤'; ?></div>
            <div>
                <p class="profile-name"><?php echo htmlspecialchars(mb_substr($username, 0, 18)); ?></p>
                <p class="profile-email"><?php echo htmlspecialchars(mb_substr($useremail, 0, 26)); ?></p>
            </div>
        </div>
        <span class="role-badge"><?php echo htmlspecialchars($role['label']); ?></span>
        <a href="../logout.php"><button class="logout-btn">Log Out</button></a>
    </div>

    <?php foreach ($role['menu'] as $item):
        $active  = ($item['href'] === 'settings.php') ? 'active' : '';
        $iconUri = menuIcon($item['icon']);
    ?>
    <a href="<?php echo htmlspecialchars($item['href']); ?>" class="nav-item <?php echo $active; ?>">
        <span class="nav-icon" style="background-image:url('<?php echo $iconUri; ?>')"></span>
        <span class="nav-label"><?php echo htmlspecialchars($item['label']); ?></span>
    </a>
    <?php endforeach; ?>
</nav>

<!-- ══════════════════ MAIN CONTENT ══════════════════ -->
<main class="dash-body">

    <div class="top-bar">
        <div>
            <h1 class="top-bar-title">Account Settings</h1>
            <p class="top-bar-subtitle">Manage your <?php echo htmlspecialchars($role['label']); ?> profile &amp; preferences</p>
        </div>
        <div>
            <p class="date-label">Today's Date</p>
            <p class="date-value"><?php echo date('F j, Y'); ?></p>
        </div>
    </div>

    <div class="cards-grid">

        <a href="?action=edit&id=<?php echo urlencode($userid); ?>&error=0" class="card">
            <span class="card-icon">⚙️</span>
            <h3 class="card-title">Account Settings</h3>
            <p class="card-desc">Update your name, email, phone number, gender, and change your password.</p>
        </a>

        <a href="?action=view&id=<?php echo urlencode($userid); ?>" class="card">
            <span class="card-icon">👁️</span>
            <h3 class="card-title">View Account Details</h3>
            <p class="card-desc">Review your personal information in a secure, read-only format.</p>
        </a>

        <a href="?action=drop&id=<?php echo urlencode($userid); ?>&name=<?php echo urlencode($username); ?>" class="card">
            <span class="card-icon">🗑️</span>
            <h3 class="card-title card-title-danger">Delete Account</h3>
            <p class="card-desc">Permanently remove your account. This action cannot be undone and all your data will be lost.</p>
        </a>

    </div>
</main>
</div>

<!-- ══════════════════ MODALS ══════════════════ -->
<?php
if (isset($_GET['action'], $_GET['id'])) {
    $id     = (int) $_GET['id'];
    $action = $_GET['action'];

    // Security: only the logged-in user may open their own modals
    if ($id !== (int) $userid) {
        echo '<script>window.location="settings.php";</script>';
        exit();
    }

    // Fresh fetch for modal content
    $stmt = $database->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    $f_fullname = htmlspecialchars($row['full_name']    ?? '');
    $f_username = htmlspecialchars($row['username']     ?? '');
    $f_email    = htmlspecialchars($row['email']        ?? '');
    $f_phone    = htmlspecialchars($row['phone']        ?? '');
    $f_gender   = $row['gender']                        ?? '';
    $f_role     = htmlspecialchars($row['role']         ?? '');
    $f_created  = htmlspecialchars($row['date_created'] ?? '');

    /* ── DELETE confirmation ─────────────────────── */
    if ($action === 'drop') {
        $nameget = htmlspecialchars(mb_substr($_GET['name'] ?? '', 0, 50));
        echo <<<HTML
        <div class="overlay" style="display:flex;">
            <div class="modal">
                <a class="modal-close" href="settings.php">&times;</a>
                <h2>⚠️ Delete Account</h2>
                <div class="modal-body">
                    <p>Are you sure you want to permanently delete your account?</p>
                    <p style="margin-top:10px;"><strong>Account: {$nameget}</strong></p>
                    <p style="color:#ef4444;font-weight:600;margin-top:16px;">
                        ⚠️ Warning: This cannot be undone. All your appointments, records, and personal data will be permanently removed.
                    </p>
                </div>
                <div class="btn-actions">
                    <a href="{$role['delete_page']}?id={$id}" class="btn btn-danger">Yes, Delete My Account</a>
                    <a href="settings.php" class="btn btn-outline">No, Keep Account</a>
                </div>
            </div>
        </div>
        HTML;

    /* ── VIEW details ────────────────────────────── */
    } elseif ($action === 'view') {
        $phone_d  = $f_phone  ?: '—';
        $gender_d = $f_gender ?: '—';
        echo <<<HTML
        <div class="overlay" style="display:flex;">
            <div class="modal">
                <a class="modal-close" href="settings.php">&times;</a>
                <h2>👤 Account Details</h2>
                <div class="modal-body">
                    <table class="dtable">
                        <tr><td class="dlabel">Full Name</td>    <td class="dvalue">{$f_fullname}</td></tr>
                        <tr><td class="dlabel">Username</td>     <td class="dvalue">{$f_username}</td></tr>
                        <tr><td class="dlabel">Email</td>        <td class="dvalue">{$f_email}</td></tr>
                        <tr><td class="dlabel">Phone</td>        <td class="dvalue">{$phone_d}</td></tr>
                        <tr><td class="dlabel">Gender</td>       <td class="dvalue">{$gender_d}</td></tr>
                        <tr><td class="dlabel">Role</td>         <td class="dvalue">{$f_role}</td></tr>
                        <tr><td class="dlabel">Member Since</td> <td class="dvalue">{$f_created}</td></tr>
                    </table>
                </div>
                <div class="btn-actions">
                    <a href="settings.php" class="btn btn-primary">Close</a>
                </div>
            </div>
        </div>
        HTML;

    /* ── EDIT form ───────────────────────────────── */
    } elseif ($action === 'edit') {
        $error_1 = $_GET['error'] ?? '0';
        $errors  = [
            '1' => '<div class="alert-error">⚠️ That email address is already registered to another account.</div>',
            '2' => '<div class="alert-error">⚠️ Passwords do not match. Please try again.</div>',
            '3' => '<div class="alert-error">⚠️ An unexpected error occurred. Please try again.</div>',
            '0' => '', '4' => '',
        ];
        $err_html = $errors[$error_1] ?? '';

        $gm = ($f_gender === 'Male')   ? 'selected' : '';
        $gf = ($f_gender === 'Female') ? 'selected' : '';

        if ($error_1 !== '4') {
            echo <<<HTML
            <div class="overlay" style="display:flex;">
                <div class="modal">
                    <a class="modal-close" href="settings.php">&times;</a>
                    <h2>✏️ Edit Account Details</h2>
                    {$err_html}
                    <form action="{$role['edit_page']}" method="POST">
                        <input type="hidden" name="id00"     value="{$id}">
                        <input type="hidden" name="oldemail" value="{$f_email}">
                        <input type="hidden" name="role"     value="{$f_role}">

                        <!-- Personal Information -->
                        <div class="form-section">
                            <p class="form-section-title">Personal Information</p>

                            <label class="form-label">User ID</label>
                            <input type="text" class="form-input" value="#{$id} (auto-generated)" readonly>

                            <label for="fullname" class="form-label">Full Name</label>
                            <input type="text" id="fullname" name="full_name" class="form-input"
                                placeholder="Enter your full name" value="{$f_fullname}" required>

                            <label for="uname" class="form-label">Username</label>
                            <input type="text" id="uname" name="username" class="form-input"
                                placeholder="Enter a username" value="{$f_username}" required>

                            <label for="gender" class="form-label">Gender</label>
                            <select id="gender" name="gender" class="form-select">
                                <option value="">— Select Gender —</option>
                                <option value="Male"   {$gm}>Male</option>
                                <option value="Female" {$gf}>Female</option>
                            </select>
                        </div>

                        <!-- Contact Information -->
                        <div class="form-section">
                            <p class="form-section-title">Contact Information</p>

                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="form-input"
                                placeholder="Enter your email address" value="{$f_email}" required>

                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-input"
                                placeholder="e.g. 0712 345 678" value="{$f_phone}">
                        </div>

                        <!-- Security -->
                        <div class="form-section">
                            <p class="form-section-title">Security</p>

                            <label for="password" class="form-label">New Password</label>
                            <input type="password" id="password" name="password" class="form-input"
                                placeholder="Enter a new password">
                            <span class="form-hint">Leave blank to keep your current password.</span>

                            <label for="cpassword" class="form-label">Confirm New Password</label>
                            <input type="password" id="cpassword" name="cpassword" class="form-input"
                                placeholder="Confirm your new password">
                        </div>

                        <div class="btn-actions">
                            <a href="settings.php" class="btn btn-outline">Cancel</a>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
            HTML;

        } else {
            // Success (edit-user.php redirects back with ?error=4 on success)
            echo <<<HTML
            <div class="overlay" style="display:flex;">
                <div class="modal">
                    <a class="modal-close" href="settings.php">&times;</a>
                    <div class="success-wrap">
                        <span class="success-icon">✅</span>
                        <h2 style="color:var(--primary);margin-bottom:14px;">Changes Saved!</h2>
                        <p>Your account details have been successfully updated.</p>
                        <p style="color:#64748b;margin-top:12px;">
                            <strong>Note:</strong> If you changed your email address, please log out
                            and sign in again with your new email.
                        </p>
                        <div class="btn-actions">
                            <a href="settings.php"  class="btn btn-primary">Continue</a>
                            <a href="../logout.php" class="btn btn-outline">Logout Now</a>
                        </div>
                    </div>
                </div>
            </div>
            HTML;
        }
    }
}
?>
</body>
</html>