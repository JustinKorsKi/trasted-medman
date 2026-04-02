<?php
require_once 'includes/config.php';

if(!isset($_GET['code'])) {
    $_SESSION['flash'] = ['message' => 'Facebook login failed: No authorization code received.', 'type' => 'error'];
    header('Location: login.php');
    exit();
}

$code = $_GET['code'];

// Exchange code for access token
$token_url = 'https://graph.facebook.com/v18.0/oauth/access_token';
$params = [
    'client_id' => FACEBOOK_APP_ID,
    'client_secret' => FACEBOOK_APP_SECRET,
    'redirect_uri' => FACEBOOK_REDIRECT_URI,
    'code' => $code
];

// Use cURL to get access token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url . '?' . http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if($http_code != 200) {
    $_SESSION['flash'] = ['message' => 'Failed to get access token from Facebook.', 'type' => 'error'];
    header('Location: login.php');
    exit();
}

$token_data = json_decode($response, true);

if(!isset($token_data['access_token'])) {
    $_SESSION['flash'] = ['message' => 'Invalid response from Facebook.', 'type' => 'error'];
    header('Location: login.php');
    exit();
}

// Get user info from Facebook
$userinfo_url = 'https://graph.facebook.com/me?fields=id,name,picture&access_token=' . $token_data['access_token'];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $userinfo_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$user_data = json_decode($response, true);

if(!isset($user_data['id'])) {
    $_SESSION['flash'] = ['message' => 'Failed to get user info from Facebook.', 'type' => 'error'];
    header('Location: login.php');
    exit();
}

$facebook_id = $user_data['id'];
$name = $user_data['name'];
$picture = isset($user_data['picture']['data']['url']) ? $user_data['picture']['data']['url'] : null;

// Check if user exists with this Facebook ID
$query = "SELECT * FROM users WHERE facebook_id = '$facebook_id'";
$result = mysqli_query($conn, $query);

if(mysqli_num_rows($result) == 1) {
    // User exists - log them in
    $user = mysqli_fetch_assoc($result);
    
    // Update last login
    $ip = $_SERVER['REMOTE_ADDR'];
    mysqli_query($conn, "UPDATE users SET last_login = NOW(), last_login_ip = '$ip' WHERE id = {$user['id']}");
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    
    $_SESSION['flash'] = ['message' => 'Welcome back! Logged in with Facebook.', 'type' => 'success'];
    header('Location: dashboard.php');
    exit();
}

// Check if user exists with this email (using facebook_id as email)
$email = $facebook_id . '@facebook.local';
$query = "SELECT * FROM users WHERE email = '$email'";
$result = mysqli_query($conn, $query);

if(mysqli_num_rows($result) == 1) {
    // User exists - link Facebook account
    $user = mysqli_fetch_assoc($result);
    
    // Update with Facebook ID
    $update = "UPDATE users SET facebook_id = '$facebook_id', avatar = '$picture' WHERE id = {$user['id']}";
    mysqli_query($conn, $update);
    
    // Update last login
    $ip = $_SERVER['REMOTE_ADDR'];
    mysqli_query($conn, "UPDATE users SET last_login = NOW(), last_login_ip = '$ip' WHERE id = {$user['id']}");
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    
    $_SESSION['flash'] = ['message' => 'Your Facebook account has been linked. Welcome back!', 'type' => 'success'];
    header('Location: dashboard.php');
    exit();
}

// New user - store data in session and redirect to role selection
$_SESSION['facebook_data'] = [
    'id' => $facebook_id,
    'name' => $name,
    'picture' => $picture
];

header('Location: facebook-role-select.php');
exit();
?>