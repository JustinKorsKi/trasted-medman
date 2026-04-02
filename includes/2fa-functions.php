<?php
/**
 * Two-Factor Authentication Helper Functions
 */

// Include the Google Authenticator library
require_once __DIR__ . '/GoogleAuthenticator.php';

function generate2FASecret($user_id) {
    global $conn;
    
    $ga = new PHPGangsta_GoogleAuthenticator();
    $secret = $ga->createSecret();
    
    // Store in database
    $secret_escaped = mysqli_real_escape_string($conn, $secret);
    mysqli_query($conn, "UPDATE users SET two_factor_secret = '$secret_escaped' WHERE id = $user_id");
    
    return $secret;
}

function get2FASecret($user_id) {
    global $conn;
    
    $query = mysqli_query($conn, "SELECT two_factor_secret FROM users WHERE id = $user_id");
    $user = mysqli_fetch_assoc($query);
    
    return $user['two_factor_secret'] ?? null;
}

function generate2FAQRCode($user_id, $username, $secret) {
    $ga = new PHPGangsta_GoogleAuthenticator();
    
    // Generate QR code URL
    $qrCodeUrl = $ga->getQRCodeGoogleUrl(
        'Trusted Midman: ' . $username,
        $secret,
        'Trusted Midman'
    );
    
    return $qrCodeUrl;
}

function verify2FACode($user_id, $code) {
    global $conn;
    
    $secret = get2FASecret($user_id);
    if (!$secret) return false;
    
    $ga = new PHPGangsta_GoogleAuthenticator();
    
    // Clean the code - remove any non-numeric characters
    $code = preg_replace('/[^0-9]/', '', trim($code));
    
    // Ensure it's 6 digits and pad with leading zeros if needed
    $code = str_pad($code, 6, '0', STR_PAD_LEFT);
    
    // Get current time slice
    $timeSlice = floor(time() / 30);
    
    // Check current and adjacent time slots (allow 2 minutes drift)
    for ($i = -4; $i <= 4; $i++) {
        $calculatedCode = $ga->getCode($secret, $timeSlice + $i);
        // Use string comparison to preserve leading zeros
        if ((string)$calculatedCode === (string)$code) {
            return true;
        }
    }
    
    return false;
}

function generateBackupCodes($user_id) {
    $codes = [];
    for ($i = 0; $i < 8; $i++) {
        $codes[] = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10)); // 10-character codes
    }
    
    global $conn;
    $codes_json = json_encode($codes);
    $codes_escaped = mysqli_real_escape_string($conn, $codes_json);
    
    mysqli_query($conn, "UPDATE users SET two_factor_backup_codes = '$codes_escaped' WHERE id = $user_id");
    
    return $codes;
}

function verifyBackupCode($user_id, $code) {
    global $conn;
    
    $query = mysqli_query($conn, "SELECT two_factor_backup_codes FROM users WHERE id = $user_id");
    $user = mysqli_fetch_assoc($query);
    
    if (!$user['two_factor_backup_codes']) return false;
    
    $codes = json_decode($user['two_factor_backup_codes'], true);
    $index = array_search($code, $codes);
    
    if ($index !== false) {
        // Remove used code
        unset($codes[$index]);
        $codes = array_values($codes);
        $codes_json = json_encode($codes);
        $codes_escaped = mysqli_real_escape_string($conn, $codes_json);
        mysqli_query($conn, "UPDATE users SET two_factor_backup_codes = '$codes_escaped' WHERE id = $user_id");
        
        return true;
    }
    
    return false;
}

function is2FAEnabled($user_id) {
    global $conn;
    
    $query = mysqli_query($conn, "SELECT two_factor_enabled FROM users WHERE id = $user_id");
    $user = mysqli_fetch_assoc($query);
    
    return $user['two_factor_enabled'] ?? false;
}
?>