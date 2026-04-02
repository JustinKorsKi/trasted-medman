<?php
require_once 'includes/config.php';

if(!isset($_GET['code'])) {
    $_SESSION['flash'] = ['message' => 'Google login failed: No authorization code received.', 'type' => 'error'];
    header('Location: login.php');
    exit();
}

$code = $_GET['code'];

// Exchange code for access token
$token_url = 'https://oauth2.googleapis.com/token';
$params = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code'
];

// Use cURL to get access token
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if($http_code != 200) {
    $_SESSION['flash'] = ['message' => 'Failed to get access token from Google.', 'type' => 'error'];
    header('Location: login.php');
    exit();
}

$token_data = json_decode($response, true);

if(!isset($token_data['access_token'])) {
    $_SESSION['flash'] = ['message' => 'Invalid response from Google.', 'type' => 'error'];
    header('Location: login.php');
    exit();
}

// Get user info from Google
$userinfo_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $userinfo_url);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token_data['access_token']
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$user_data = json_decode($response, true);

if(!isset($user_data['id'])) {
    $_SESSION['flash'] = ['message' => 'Failed to get user info from Google.', 'type' => 'error'];
    header('Location: login.php');
    exit();
}

$google_id = $user_data['id'];
$email = $user_data['email'];
$name = $user_data['name'];
$picture = isset($user_data['picture']) ? $user_data['picture'] : null;

// Check if user exists with this Google ID
$query = "SELECT * FROM users WHERE google_id = '$google_id'";
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
    
    $_SESSION['flash'] = ['message' => 'Welcome back! Logged in with Google.', 'type' => 'success'];
    header('Location: dashboard.php');
    exit();
}

// Check if user exists with this email
$query = "SELECT * FROM users WHERE email = '$email'";
$result = mysqli_query($conn, $query);

if(mysqli_num_rows($result) == 1) {
    // User exists - link Google account
    $user = mysqli_fetch_assoc($result);
    
    // Update with Google ID
    $update = "UPDATE users SET google_id = '$google_id', avatar = '$picture' WHERE id = {$user['id']}";
    mysqli_query($conn, $update);
    
    // Update last login
    $ip = $_SERVER['REMOTE_ADDR'];
    mysqli_query($conn, "UPDATE users SET last_login = NOW(), last_login_ip = '$ip' WHERE id = {$user['id']}");
    
    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    
    $_SESSION['flash'] = ['message' => 'Your Google account has been linked. Welcome back!', 'type' => 'success'];
    header('Location: dashboard.php');
    exit();
}

// New user - store data in session and redirect to role selection
$_SESSION['google_data'] = [
    'id' => $google_id,
    'email' => $email,
    'name' => $name,
    'picture' => $picture
];

header('Location: google-role-select.php');
exit();
?>