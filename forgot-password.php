<?php
require_once 'includes/config.php';
require_once 'includes/mailer.php';

if(isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Check if email exists
    $query = "SELECT id, username FROM users WHERE email = '$email'";
    $result = mysqli_query($conn, $query);
    
    if(mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Generate reset token
        $reset_token = generateToken(32);
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Save token to database
        $update = "UPDATE users SET 
                   reset_token = '$reset_token',
                   reset_expires = '$expires' 
                   WHERE id = {$user['id']}";
        
        if(mysqli_query($conn, $update)) {
            // Send reset email
            $reset_link = "http://localhost/trusted-midman/reset-password.php?token=$reset_token";
            
            $subject = "Reset Your Password - Trusted Midman";
            
            $body = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                    .button { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; }
                    .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Password Reset Request</h2>
                    </div>
                    <div class='content'>
                        <p>Hello <strong>{$user['username']}</strong>,</p>
                        <p>We received a request to reset your password. Click the button below to proceed:</p>
                        <p style='text-align: center;'>
                            <a href='$reset_link' class='button'>Reset Password</a>
                        </p>
                        <p>Or copy and paste this link:</p>
                        <p>$reset_link</p>
                        <p>This link will expire in 1 hour.</p>
                        <p>If you didn't request this, please ignore this email.</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; 2026 Trusted Midman. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            if(sendEmail($email, $subject, $body)) {
                $success = 'Password reset link has been sent to your email.';
            } else {
                $error = 'Failed to send email. Please try again.';
            }
        } else {
            $error = 'Failed to generate reset link. Please try again.';
        }
    } else {
        // Don't reveal if email exists or not (security)
        $success = 'If your email exists in our system, you will receive a reset link.';
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — Trusted Midman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0d0f14;
            --surface:   #13161e;
            --surface2:  #1a1e28;
            --border:    rgba(255,255,255,0.07);
            --border2:   rgba(255,255,255,0.13);
            --gold:      #f0a500;
            --gold-dim:  rgba(240,165,0,0.14);
            --gold-glow: rgba(240,165,0,0.32);
            --teal:      #00d4aa;
            --teal-dim:  rgba(0,212,170,0.12);
            --red:       #ff4d6d;
            --red-dim:   rgba(255,77,109,0.12);
            --text:      #e8eaf0;
            --text-muted:#7a7f95;
            --text-dim:  #4a4f65;
            --radius-sm: 8px;
            --radius:    14px;
            --font-head: 'Syne', sans-serif;
            --font-body: 'DM Sans', sans-serif;
        }

        html { height: 100%; }

        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 88px 24px 40px;
            position: relative;
            overflow-x: hidden;
        }

        .bg-glow1 {
            position: fixed; top: -100px; right: -100px;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(240,165,0,0.09) 0%, transparent 65%);
            pointer-events: none; z-index: 0;
        }
        .bg-glow2 {
            position: fixed; bottom: -100px; left: -80px;
            width: 460px; height: 460px;
            background: radial-gradient(circle, rgba(0,212,170,0.07) 0%, transparent 65%);
            pointer-events: none; z-index: 0;
        }
        .bg-grid {
            position: fixed; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.022) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.022) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 40%, black 10%, transparent 100%);
            pointer-events: none; z-index: 0;
        }

        nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 200;
            background: rgba(13,15,20,0.82);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            height: 64px;
            display: flex; align-items: center;
            padding: 0 clamp(20px,5vw,60px);
        }
        .nav-inner {
            width: 100%; max-width: 1280px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
        }
        .nav-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .nav-logo-icon {
            width: 34px; height: 34px; background: var(--gold); border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            color: #0d0f14; font-size: 14px; box-shadow: 0 0 16px var(--gold-glow);
        }
        .nav-logo-text { font-family: var(--font-head); font-weight: 700; font-size: 1rem; color: var(--text); }
        .nav-links { display: flex; align-items: center; gap: 6px; }
        .nav-link {
            padding: 7px 14px; border-radius: var(--radius-sm);
            font-size: 0.85rem; font-weight: 500; text-decoration: none; color: var(--text-muted);
            transition: all 0.2s;
        }
        .nav-link:hover { color: var(--text); background: var(--surface2); }
        .nav-btn {
            padding: 8px 18px; border-radius: var(--radius-sm);
            font-family: var(--font-body); font-size: 0.85rem; font-weight: 600;
            text-decoration: none; cursor: pointer; border: none; transition: all 0.22s;
        }
        .nav-btn-ghost { background: transparent; color: var(--text-muted); border: 1px solid var(--border2); }
        .nav-btn-ghost:hover { color: var(--text); background: var(--surface2); }
        .nav-btn-gold { background: var(--gold); color: #0d0f14; box-shadow: 0 3px 14px var(--gold-glow); }
        .nav-btn-gold:hover { background: #ffb822; transform: translateY(-1px); }
        @media (max-width: 600px) { .nav-link { display: none; } }

        .card {
            position: relative; z-index: 1;
            width: 100%; max-width: 480px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 32px 80px rgba(0,0,0,0.55);
            opacity: 0; transform: translateY(20px);
            animation: fadeUp 0.55s 0.05s ease forwards;
        }
        @keyframes fadeUp { to { opacity:1; transform:translateY(0); } }

        .card-head {
            padding: 32px 36px 26px;
            border-bottom: 1px solid var(--border);
            position: relative; overflow: hidden;
        }
        .card-head::before {
            content: '';
            position: absolute; top: -60px; right: -60px;
            width: 260px; height: 260px;
            background: radial-gradient(circle, var(--gold-glow) 0%, transparent 65%);
            pointer-events: none;
        }

        .brand {
            display: flex; align-items: center; gap: 11px;
            text-decoration: none; margin-bottom: 22px;
        }
        .brand-icon {
            width: 34px; height: 34px; background: var(--gold); border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            color: #0d0f14; font-size: 14px; box-shadow: 0 0 16px var(--gold-glow);
        }
        .brand-name { font-family: var(--font-head); font-weight: 700; font-size: 0.95rem; color: var(--text); }

        .head-title {
            font-family: var(--font-head);
            font-size: 1.55rem; font-weight: 800; color: var(--text);
            line-height: 1.1; margin-bottom: 5px;
            position: relative; z-index: 1;
        }
        .head-title span { color: var(--gold); }
        .head-sub { font-size: 0.85rem; color: var(--text-muted); position: relative; z-index: 1; }

        .card-body { padding: 26px 36px 32px; }

        .alert {
            display: flex; align-items: flex-start; gap: 10px;
            border-radius: var(--radius-sm); padding: 12px 14px;
            font-size: 0.85rem; margin-bottom: 20px; line-height: 1.5;
        }
        .alert i { font-size: 0.9rem; margin-top: 2px; flex-shrink: 0; }
        .alert-error   { background: var(--red-dim);  border: 1px solid rgba(255,77,109,0.22);  color: var(--red); }
        .alert-success { background: var(--teal-dim); border: 1px solid rgba(0,212,170,0.22);   color: var(--teal); }
        .alert-info    { background: var(--gold-dim); border: 1px solid rgba(240,165,0,0.22);   color: var(--gold); }

        .form-group { margin-bottom: 20px; }

        .form-label {
            display: block; margin-bottom: 7px;
            font-size: 0.72rem; font-weight: 700;
            letter-spacing: 0.09em; text-transform: uppercase;
            color: var(--text-muted);
        }

        .input-wrap { position: relative; }

        .input-icon {
            position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
            color: var(--text-dim); font-size: 0.82rem; pointer-events: none;
            transition: color 0.2s;
        }
        .input-wrap:focus-within .input-icon { color: var(--gold); }

        .form-input {
            width: 100%;
            padding: 12px 16px 12px 40px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-family: var(--font-body); font-size: 0.9rem;
            transition: all 0.2s;
        }
        .form-input:focus {
            outline: none; border-color: var(--gold);
            box-shadow: 0 0 0 3px var(--gold-dim);
        }

        .btn-submit {
            width: 100%; padding: 12px 20px;
            background: var(--gold); color: #0d0f14;
            border: none; border-radius: var(--radius-sm);
            font-family: var(--font-body); font-size: 0.9rem; font-weight: 700;
            cursor: pointer; letter-spacing: 0.02em;
            box-shadow: 0 4px 20px var(--gold-glow);
            transition: all 0.24s ease;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-submit:hover { background: #ffb822; transform: translateY(-2px); box-shadow: 0 8px 28px var(--gold-glow); }

        .card-foot { text-align: center; font-size: 0.83rem; color: var(--text-muted); margin-top: 20px; }
        .card-foot a { color: var(--gold); text-decoration: none; font-weight: 600; transition: color 0.2s; }
        .card-foot a:hover { color: #ffb822; }

        @media (max-width: 540px) {
            .card-head, .card-body { padding-left: 22px; padding-right: 22px; }
        }
    </style>
</head>
<body>
    <div class="bg-glow1"></div>
    <div class="bg-glow2"></div>
    <div class="bg-grid"></div>

    <nav>
        <div class="nav-inner">
            <a href="index.php" class="nav-logo">
                <div class="nav-logo-icon"><i class="fas fa-shield-halved"></i></div>
                <span class="nav-logo-text">Trusted Midman</span>
            </a>
            <div class="nav-links">
                <a href="login.php" class="nav-btn nav-btn-ghost">Sign In</a>
                <a href="register.php" class="nav-btn nav-btn-gold">Get Started</a>
            </div>
        </div>
    </nav>

    <div class="card">
        <div class="card-head">
            <a href="index.php" class="brand">
                <div class="brand-icon"><i class="fas fa-shield-halved"></i></div>
                <span class="brand-name">Trusted Midman</span>
            </a>
            <h1 class="head-title">Forgot <span>password?</span></h1>
            <p class="head-sub">Enter your email to receive a reset link.</p>
        </div>

        <div class="card-body">
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-circle-check"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <div class="card-foot">
                    <a href="login.php">Return to Login</a>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <div class="input-wrap">
                            <i class="input-icon fas fa-envelope"></i>
                            <input type="email" id="email" name="email" class="form-input" 
                                   placeholder="your@email.com" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Send Reset Link
                    </button>
                </form>

                <div class="card-foot">
                    <a href="login.php">Back to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>