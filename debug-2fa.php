<?php
require_once 'includes/config.php';
require_once 'includes/2fa-functions.php';

$user_id = $_SESSION['user_id'] ?? 6; // Change to your user ID

echo "<h2>🔐 2FA Debug</h2>";

$secret = get2FASecret($user_id);
echo "Secret: " . $secret . "<br>";

$ga = new PHPGangsta_GoogleAuthenticator();

// Current time
$time = time();
$timeSlice = floor($time / 30);
echo "Server time: " . date('Y-m-d H:i:s', $time) . "<br>";
echo "Time slice: " . $timeSlice . "<br>";

// Generate codes for current and adjacent time slots
echo "<h3>Codes for current and nearby time slots:</h3>";
for ($i = -4; $i <= 4; $i++) {
    $code = $ga->getCode($secret, $timeSlice + $i);
    $offset = $i * 30;
    echo "Slot " . ($i > 0 ? "+$i" : $i) . " ($offset sec): <strong>$code</strong><br>";
}

// Check if 2FA is enabled
$enabled = is2FAEnabled($user_id);
echo "<p>2FA Enabled: " . ($enabled ? '✅ YES' : '❌ NO') . "</p>";
?>