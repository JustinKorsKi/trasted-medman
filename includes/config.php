<?php
// ============================================
// CONFIG.PHP - For Railway
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');

// ============================================
// DATABASE CONNECTION - RAILWAY
// ============================================

$host = getenv('MYSQLHOST');
$dbname = getenv('MYSQLDATABASE');
$username = getenv('MYSQLUSER');
$password = getenv('MYSQLPASSWORD');

$conn = mysqli_connect($host, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8");

// ============================================
// SECURITY CONSTANTS
// ============================================

define('SECRET_KEY', getenv('SECRET_KEY') ?: 'your-secret-key-here-change-this-123!@#');
define('CSRF_KEY', getenv('CSRF_KEY') ?: 'your-csrf-key-here-change-this-456!@#');

// ============================================
// OAUTH CONSTANTS - Dynamic URLs
// ============================================

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host_name = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $protocol . $host_name;

// Fix for localhost path
if(strpos($host_name, 'localhost') !== false) {
    $base_url .= '/trusted-midman';
}

// BASE_URL for email verification links
if (!defined('BASE_URL')) {
    define('BASE_URL', getenv('BASE_URL') ?: $base_url);
}

define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: 'your-google-client-id-here');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: 'your-google-client-secret-here');
define('GOOGLE_REDIRECT_URI', $base_url . '/google-callback.php');

define('FACEBOOK_APP_ID', getenv('FACEBOOK_APP_ID') ?: 'your-facebook-app-id-here');
define('FACEBOOK_APP_SECRET', getenv('FACEBOOK_APP_SECRET') ?: 'your-facebook-app-secret-here');
define('FACEBOOK_REDIRECT_URI', $base_url . '/facebook-callback.php');

// ============================================
// EMAIL SETTINGS
// ============================================

define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp-relay.brevo.com');
define('SMTP_PORT', intval(getenv('SMTP_PORT') ?: 587));
define('SMTP_USER', getenv('SMTP_USER') ?: 'your-email@smtp-brevo.com');
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'your-smtp-key-here');
define('SMTP_FROM', 'justinescalera042@gmail.com');
define('SMTP_FROM_NAME', 'Trusted Midman');

// ============================================
// SECURITY SETTINGS
// ============================================

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 15);
define('REMEMBER_ME_DAYS', 30);
define('VERIFICATION_TOKEN_EXPIRY', 24);

// ============================================
// SESSION MANAGEMENT
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// CORE HELPER FUNCTIONS
// ============================================

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function checkRememberMe() {
    global $conn;
    
    if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
        $token = mysqli_real_escape_string($conn, $_COOKIE['remember_token']);
        
        $query = "SELECT s.*, u.* FROM user_sessions s 
                  JOIN users u ON s.user_id = u.id 
                  WHERE s.token = '$token' AND s.expires_at > NOW()";
        $result = mysqli_query($conn, $query);
        
        if ($row = mysqli_fetch_assoc($result)) {
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['full_name'] = $row['full_name'];
            return true;
        } else {
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }
    return false;
}

function checkLoginAttempts($email, $ip) {
    global $conn;
    
    $email = mysqli_real_escape_string($conn, $email);
    $ip = mysqli_real_escape_string($conn, $ip);
    
    mysqli_query($conn, "DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    
    $query = "SELECT COUNT(*) as attempts FROM login_attempts 
              WHERE email = '$email' AND ip_address = '$ip' AND success = 0 
              AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    
    return $row['attempts'] >= MAX_LOGIN_ATTEMPTS;
}

function recordLoginAttempt($email, $ip, $success) {
    global $conn;
    
    $email = mysqli_real_escape_string($conn, $email);
    $ip = mysqli_real_escape_string($conn, $ip);
    $success = $success ? 1 : 0;
    
    mysqli_query($conn, "INSERT INTO login_attempts (email, ip_address, success) 
                         VALUES ('$email', '$ip', $success)");
}

function isUserLocked($email) {
    global $conn;
    
    $email = mysqli_real_escape_string($conn, $email);
    $query = "SELECT locked_until FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $query);
    
    if ($row = mysqli_fetch_assoc($result)) {
        if ($row['locked_until'] && strtotime($row['locked_until']) > time()) {
            return true;
        }
    }
    return false;
}

function isLoggedIn() {
    if (isset($_SESSION['user_id'])) {
        return true;
    }
    return checkRememberMe();
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserRole() {
    return $_SESSION['role'] ?? '';
}

function redirect($url, $message = null, $type = 'success') {
    if ($message) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    }
    header("Location: $url");
    exit();
}

function displayFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        $type = $flash['type'];
        $message = $flash['message'];
        unset($_SESSION['flash']);
        return "<div class='alert alert-$type'>$message</div>";
    }
    return '';
}

// ============================================
// ROLE-BASED DASHBOARD REDIRECTS
// ============================================

function getDashboardUrl() {
    if (!isset($_SESSION['user_id'])) {
        return 'login.php';
    }
    
    $role = $_SESSION['role'] ?? 'buyer';
    
    switch($role) {
        case 'admin':
            return 'admin/dashboard.php';
        case 'midman':
            return 'midman-dashboard.php';
        case 'seller':
            return 'seller-dashboard.php';
        case 'buyer':
            return 'buyer-dashboard.php';
        default:
            return 'dashboard.php';
    }
}

function redirectToDashboard() {
    header('Location: ' . getDashboardUrl());
    exit();
}

// ============================================
// ROLE CHECK FUNCTIONS
// ============================================

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

function isMidman() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'midman';
}

function isSeller() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'seller';
}

function isBuyer() {
    return isset($_SESSION['role'] ) && $_SESSION['role'] == 'buyer';
}

function requireRole($role) {
    if (!isset($_SESSION['user_id'])) {
        redirect('login.php');
    }
    
    $allowed_roles = is_array($role) ? $role : [$role];
    
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        redirect(getDashboardUrl());
    }
}

// ============================================
// USER PERMISSION CHECKS
// ============================================

function canViewTransaction($transaction, $user_id) {
    return ($transaction['buyer_id'] == $user_id || 
            $transaction['seller_id'] == $user_id || 
            $transaction['midman_id'] == $user_id ||
            isAdmin());
}

function canEditProduct($product, $user_id) {
    return ($product['seller_id'] == $user_id || isAdmin());
}

// ============================================
// CALL REMEMBER ME CHECK
// ============================================
checkRememberMe();
?>
