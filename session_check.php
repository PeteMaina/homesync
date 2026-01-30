<?php
require_once 'config.php';

function checkSessionTimeout() {
    // Check if session has started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }

    // Check for session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        // Session has expired, destroy it
        session_unset();
        session_destroy();
        return false;
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();
    return true;
}

function requireLogin() {
    if (!checkSessionTimeout()) {
        header("Location: auth.html");
        exit();
    }
}

// Auto-check session on include
checkSessionTimeout();
?>
