<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = SMTP_PORT;
        $mail->Timeout    = 10;
        $mail->SMTPDebug  = 2;  // ← set to 0 when working

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Email failed: " . $mail->ErrorInfo);
        return false;
    }
}

function sendVerificationEmail($email, $token, $username) {
    $subject = "Verify Your Email - Trusted Midman";
    $verification_link = BASE_URL . "/verify-email.php?token=" . urlencode($token);

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
            <div class='header'><h2>Welcome to Trusted Midman!</h2></div>
            <div class='content'>
                <p>Hello <strong>$username</strong>,</p>
                <p>Please verify your email address by clicking the button below:</p>
                <p style='text-align: center;'>
                    <a href='$verification_link' class='button'>Verify Email Address</a>
                </p>
                <p>Or copy this link: <a href='$verification_link'>$verification_link</a></p>
                <p>This link expires in 24 hours.</p>
            </div>
            <div class='footer'><p>&copy; 2026 Trusted Midman. All rights reserved.</p></div>
        </div>
    </body>
    </html>";

    return sendEmail($email, $subject, $body);
}
?>
