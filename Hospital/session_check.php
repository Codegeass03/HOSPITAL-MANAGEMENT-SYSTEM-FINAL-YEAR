<?php

// ⏳ Set session timeout duration (15 minutes)
$timeout_duration = 900; // 900 seconds = 15 minutes

if (!isset($_SESSION['user_id'])) {
    // No active session → redirect to login
    header("Location: /Hospital/public/login.php?error=sessionexpired");
    exit;
}

// Check if session has expired
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: /Hospital/index.php?message=sessionexpired");
    exit;
}

// 🔄 Update last activity timestamp on every request
$_SESSION['last_activity'] = time();
