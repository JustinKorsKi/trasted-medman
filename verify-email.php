<?php
require_once 'includes/config.php';

$error = '';
$success = '';
$show_form = false;

// Check if token is provided
if(isset($_GET['token'])) {
    $token = mysqli_real_escape_string($conn, $_GET['token']);
    
    // Find user with this token
    $query = "SELECT id, username, email, email_verified, email_verification_expires 
              FROM users 
              WHERE email_verification_token = '$token' 
              LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if(mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Check if already verified
        if($user['email_verified'] == 1) {
            $success = 'Your email is already verified. You can now login.';
        } else {
            // Check if token has expired
            if(strtotime($user['email_verification_expires']) < time()) {
                $error = 'Verification link has expired. Please request a new one.';
                $show_form = true;
                $_SESSION['verify_email'] = $user['email'];
            } else {
                // Verify the email
                $update = "UPDATE users SET 
                          email_verified = 1,
                          email_verification_token = NULL,
                          email_verification_expires = NULL 
                          WHERE id = {$user['id']}";
                
                if(mysqli_query($conn, $update)) {
                    $success = 'Email verified successfully! You can now login to your account.';
                } else {
                    $error = 'Verification failed. Please try again.';
                }
            }
        }
    } else {
        $error = 'Invalid verification link.';
    }
}

// Handle resend verification
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resend'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Check if user exists and not verified
    $query = "SELECT id, username, email_verified FROM users WHERE email = '$email' LIMIT 1";
    $result = mysqli_query($conn, $query);
    
    if(mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        if($user['email_verified'] == 1) {
            $success = 'This email is already verified. You can login.';
        } else {
            // Generate new token
            $new_token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            $update = "UPDATE users SET 
                      email_verification_token = '$new_token',
                      email_verification_expires = '$expires' 
                      WHERE id = {$user['id']}";
            
            if(mysqli_query($conn, $update)) {
                // Send new verification email
                require_once 'includes/mailer.php';
                if(sendVerificationEmail($email, $new_token, $user['username'])) {
                    $success = 'New verification link has been sent to your email.';
                } else {
                    $error = 'Failed to send email. Please try again.';
                }
            } else {
                $error = 'Failed to generate new verification link.';
            }
        }
    } else {
        $error = 'Email not found in our system.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email — Trusted Midman</title>
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

        /* ── BG ── */
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

        /* ── NAV ── */
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

        /* ── CARD ── */
        .card {
            position: relative; z-index: 1;
            width: 100%; max-width: 520px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 32px 80px rgba(0,0,0,0.55);
            opacity: 0; transform: translateY(20px);
            animation: fadeUp 0.55s 0.05s ease forwards;
        }
        @keyframes fadeUp { to { opacity:1; transform:translateY(0); } }

        /* ── CARD HEAD ── */
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

        /* ── CARD BODY ── */
        .card-body { padding: 26px 36px 32px; }

        /* ── ALERTS ── */
        .alert {
            display: flex; align-items: flex-start; gap: 10px;
            border-radius: var(--radius-sm); padding: 12px 14px;
            font-size: 0.85rem; margin-bottom: 20px; line-height: 1.5;
        }
        .alert i { font-size: 0.9rem; margin-top: 2px; flex-shrink: 0; }
        .alert-error   { background: var(--red-dim);  border: 1px solid rgba(255,77,109,0.22);  color: var(--red); }
        .alert-success { background: var(--teal-dim); border: 1px solid rgba(0,212,170,0.22);   color: var(--teal); }
        .alert-info    { background: var(--gold-dim); border: 1px solid rgba(240,165,0,0.22);   color: var(--gold); }

        /* ── VERIFICATION ICON ── */
        .verify-icon {
            text-align: center;
            margin: 20px 0;
            font-size: 4rem;
        }
        .verify-icon.success { color: var(--teal); }
        .verify-icon.error { color: var(--red); }
        .verify-icon.pending { color: var(--gold); }

        /* ── FORM ── */
        .form-group { margin-bottom: 16px; }

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
            padding: 10px 16px 10px 37px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-family: var(--font-body); font-size: 0.875rem;
            transition: all 0.2s;
        }
        .form-input:focus {
            outline: none; border-color: var(--gold);
            box-shadow: 0 0 0 3px var(--gold-dim);
        }

        /* ── BUTTON ── */
        .btn-submit {
            width: 100%; padding: 12px 20px; margin-top: 6px;
            background: var(--gold); color: #0d0f14;
            border: none; border-radius: var(--radius-sm);
            font-family: var(--font-body); font-size: 0.9rem; font-weight: 700;
            cursor: pointer; letter-spacing: 0.02em;
            box-shadow: 0 4px 20px var(--gold-glow);
            transition: all 0.24s ease;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-submit:hover { background: #ffb822; transform: translateY(-2px); box-shadow: 0 8px 28px var(--gold-glow); }

        .btn-secondary {
            background: transparent;
            border: 1px solid var(--border2);
            color: var(--text-muted);
            box-shadow: none;
        }
        .btn-secondary:hover { background: var(--surface2); transform: translateY(-2px); }

        /* ── FOOTER ── */
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

    <!-- NAV -->
    <nav>
        <div class="nav-inner">
            <a href="index.php" class="nav-logo">
                <div class="nav-logo-icon"><i class="fas fa-shield-halved"></i></div>
                <span class="nav-logo-text">Trusted Midman</span>
            </a>
            <div class="nav-links">
                <a href="index.php" class="nav-link">Home</a>
                <a href="products.php" class="nav-link">Marketplace</a>
                <a href="login.php" class="nav-btn nav-btn-ghost">Sign In</a>
                <a href="register.php" class="nav-btn nav-btn-gold">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- CARD -->
    <div class="card">
        <!-- HEAD -->
        <div class="card-head">
            <a href="index.php" class="brand">
                <div class="brand-icon"><i class="fas fa-shield-halved"></i></div>
                <span class="brand-name">Trusted Midman</span>
            </a>
            <h1 class="head-title">Email <span>Verification</span></h1>
            <p class="head-sub">Verify your email address to access all features</p>
        </div>

        <!-- BODY -->
        <div class="card-body">

            <?php if($success): ?>
                <div class="verify-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="alert alert-success">
                    <i class="fas fa-circle-check"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <div class="card-foot">
                    <a href="login.php">Proceed to Login →</a>
                </div>
            <?php endif; ?>

            <?php if($error && !$show_form): ?>
                <div class="verify-icon error">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="alert alert-error">
                    <i class="fas fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <div class="card-foot">
                    <a href="register.php">Create New Account</a>
                </div>
            <?php endif; ?>

            <?php if($show_form): ?>
                <div class="verify-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <div class="input-wrap">
                            <i class="input-icon fas fa-envelope"></i>
                            <input type="email" id="email" name="email" class="form-input"
                                   value="<?php echo htmlspecialchars($_SESSION['verify_email'] ?? ''); ?>" 
                                   placeholder="your@email.com" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="resend" class="btn-submit">
                        <i class="fas fa-paper-plane"></i> Resend Verification Email
                    </button>
                </form>
                
                <div class="card-foot">
                    <a href="login.php">Back to Login</a>
                </div>
            <?php endif; ?>

            <?php if(!$error && !$success && !$show_form): ?>
                <div class="verify-icon pending">
                    <i class="fas fa-spinner fa-pulse"></i>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Verifying your email address...
                </div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        // Auto redirect after 5 seconds on success
        <?php if($success && strpos($success, 'verified successfully') !== false): ?>
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>