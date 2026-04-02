<?php
require_once 'includes/config.php';

// Redirect to Facebook login
$params = [
    'client_id' => FACEBOOK_APP_ID,
    'redirect_uri' => FACEBOOK_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'public_profile' // Removed 'email' since it's not available
];

$auth_url = 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query($params);

header('Location: ' . $auth_url);
exit();
?>