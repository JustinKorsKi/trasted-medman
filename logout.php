<?php
require_once 'includes/config.php';

// Clear remember me cookie
if(isset($_COOKIE['remember_token'])) {
    // Delete from database
    $token = mysqli_real_escape_string($conn, $_COOKIE['remember_token']);
    mysqli_query($conn, "DELETE FROM user_sessions WHERE token = '$token'");
    
    // Expire the cookie
    setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

// Destroy all session data
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally destroy the session
session_destroy();

// Redirect to home page
header('Location: index.php?logged_out=1');
exit();
?>