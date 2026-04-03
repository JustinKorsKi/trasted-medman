<?php
// First, include config.php to get the email constants
require_once __DIR__ . '/config.php';

if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', 'smtp.gmail.com');
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', 587);
}
if (!defined('SMTP_USER')) {
    define('SMTP_USER', '');
}
if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', '');
}
if (!defined('SMTP_FROM')) {
    define('SMTP_FROM', 'no-reply@trustedmidman.local');
}
if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', 'Trusted Midman');
}

// Then include PHPMailer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_OFF;                      // Disable debug output
        $mail->isSMTP();                                          // Send using SMTP
        $mail->Host       = SMTP_HOST;                            // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                 // Enable SMTP authentication
        $mail->Username   = SMTP_USER;                            // SMTP username
        $mail->Password   = SMTP_PASS;                            // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;       // Enable TLS encryption
        $mail->Port       = SMTP_PORT;                            // TCP port to connect to
        
        // Recipients
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);                                       // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Error: {$mail->ErrorInfo}");
        return false;
    }
}

function sendVerificationEmail($email, $token, $username) {
    $subject = "Verify Your Email - Trusted Midman";
    
    // Use BASE_URL constant instead of hardcoded localhost
    $verification_link = BASE_URL . "/verify-email.php?token=$token";
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #f0a500 0%, #d4920a 100%); color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .button { display: inline-block; padding: 10px 20px; background: #f0a500; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Welcome to Trusted Midman!</h2>
            </div>
            <div class='content'>
                <p>Hello <strong>$username</strong>,</p>
                <p>Thank you for registering! Please verify your email address by clicking the button below:</p>
                <p style='text-align: center;'>
                    <a href='$verification_link' class='button'>Verify Email Address</a>
                </p>
                <p>Or copy and paste this link:</p>
                <p><a href='$verification_link'>$verification_link</a></p>
                <p>This link will expire in 24 hours.</p>
                <p>If you didn't create an account, you can ignore this email.</p>
            </div>
            <div class='footer'>
                <p>&copy; 2026 Trusted Midman. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body);
}
?>
