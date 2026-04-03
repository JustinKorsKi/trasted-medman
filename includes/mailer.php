<?php
require_once __DIR__ . '/config.php';

function sendEmail($to, $subject, $body) {
    $apiKey = getenv('BREVO_API_KEY');
    
    $data = [
        'sender' => [
            'name'  => SMTP_FROM_NAME,
            'email' => SMTP_FROM
        ],
        'to' => [
            ['email' => $to]
        ],
        'subject'     => $subject,
        'htmlContent' => $body,
        'textContent' => strip_tags($body)
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Content-Type: application/json',
            'api-key: ' . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode($data)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 201) {
        return true;
    } else {
        error_log("Brevo API error: HTTP $httpCode — $response");
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
                <p>Please verify your email by clicking the button below:</p>
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
