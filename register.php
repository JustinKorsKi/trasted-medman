<?php
require_once 'includes/config.php';

if(isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error   = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $username  = mysqli_real_escape_string($conn, $_POST['username']);
    $email     = mysqli_real_escape_string($conn, $_POST['email']);
    $password  = $_POST['password'];
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phone     = mysqli_real_escape_string($conn, $_POST['phone']);
    $role      = mysqli_real_escape_string($conn, $_POST['role']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';
    } else {
        $check = mysqli_query($conn, "SELECT * FROM users WHERE email='$email' OR username='$username'");
        if(mysqli_num_rows($check) > 0) {
            $error = 'Username or email already exists.';
        } else {
            $hashed_password    = password_hash($password, PASSWORD_BCRYPT);
            $verification_token = bin2hex(random_bytes(32));
            $expires            = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $q = "INSERT INTO users (username, email, password, full_name, phone, role,
                  email_verification_token, email_verification_expires)
                  VALUES ('$username', '$email', '$hashed_password', '$full_name', '$phone', '$role',
                  '$verification_token', '$expires')";
            if(mysqli_query($conn, $q)) {
                // Load mailer only when needed and with timeout protection
                $emailSent = false;
                try {
                    require_once 'includes/mailer.php';
                    $emailSent = sendVerificationEmail($email, $verification_token, $username);
                } catch (Exception $e) {
                    error_log("Email failed: " . $e->getMessage());
                }

                if($emailSent) {
                    $success = 'Account created! Please check your email to verify your account.';
                } else {
                    $success = 'Account created! You can login now. (Verification email could not be sent)';
                }
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — Trusted Midman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:         #0f0c08;
            --bg2:        #130f0a;
            --surface:    #0f0b07;
            --surface2:   #16110d;
            --surface3:   #271f16;
            --border:     rgba(255,180,80,0.08);
            --border2:    rgba(255,180,80,0.15);
            --border3:    rgba(255,180,80,0.24);
            --gold:       #f0a500;
            --gold-lt:    #ffbe3a;
            --gold-dim:   rgba(240,165,0,0.13);
            --gold-glow:  rgba(240,165,0,0.30);
            --teal:       #00d4aa;
            --teal-dim:   rgba(0,212,170,0.11);
            --red:        #ff4d6d;
            --red-dim:    rgba(255,77,109,0.12);
            --blue:       #4e9fff;
            --blue-dim:   rgba(78,159,255,0.12);
            --orange:     #ff9632;
            --text:       #ffffff;
            --text-warm:  #f0e8da;
            --text-muted: #a89880;
            --text-dim:   #5a4e3a;
            --radius-sm:  10px;
            --radius:     16px;
            --radius-lg:  22px;
            --font-head:  'Barlow Condensed', sans-serif;
            --font-body:  'DM Sans', sans-serif;
        }

        html { height: 100%; }

        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--text-warm);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 88px 24px 48px;
            position: relative;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ── BACKGROUND ── */
        .bg-glow1 {
            position: fixed; top: -160px; right: -160px;
            width: 640px; height: 640px;
            background: radial-gradient(circle, rgba(255,140,20,0.18) 0%, rgba(200,80,0,0.07) 40%, transparent 65%);
            pointer-events: none; z-index: 0;
        }
        .bg-glow2 {
            position: fixed; bottom: -140px; left: -120px;
            width: 560px; height: 560px;
            background: radial-gradient(circle, rgba(180,70,0,0.14) 0%, rgba(240,130,0,0.05) 45%, transparent 65%);
            pointer-events: none; z-index: 0;
        }
        .bg-glow3 {
            position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: 900px; height: 400px;
            background: radial-gradient(ellipse, rgba(240,165,0,0.04) 0%, transparent 65%);
            pointer-events: none; z-index: 0;
        }
        .bg-grid {
            position: fixed; inset: 0;
            background-image:
                linear-gradient(rgba(255,180,60,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,180,60,0.03) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 40%, black 10%, transparent 100%);
            pointer-events: none; z-index: 0;
        }

        /* ── NAV ── */
        nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 200;
            height: 64px; display: flex; align-items: center;
            padding: 0 clamp(20px,5vw,60px);
            background: rgba(15,12,8,0.82);
            backdrop-filter: blur(24px);
            border-bottom: 1px solid var(--border);
        }
        .nav-inner {
            width: 100%; max-width: 1280px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
        }
        .nav-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .nav-logo-icon {
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, var(--gold), #e09000);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 20px var(--gold-glow), 0 4px 12px rgba(240,165,0,0.2);
    /* remove any padding or extra background */
}
.nav-logo-icon img {
    width: 80%;
    height: 80%;
    object-fit: contain;
    /* ensures the image scales without distortion */
}
        .nav-logo-text { font-family: var(--font-head); font-weight: 700; font-size: 1.1rem; color: var(--text); }
        .nav-links { display: flex; align-items: center; gap: 6px; }
        .nav-link {
            padding: 7px 14px; border-radius: var(--radius-sm);
            font-size: 0.85rem; font-weight: 500; text-decoration: none;
            color: var(--text-muted); transition: all 0.2s;
        }
        .nav-link:hover { color: var(--text-warm); background: var(--surface2); }
        .nav-btn {
            padding: 8px 18px; border-radius: var(--radius-sm);
            font-family: var(--font-body); font-size: 0.85rem; font-weight: 600;
            text-decoration: none; cursor: pointer; border: none; transition: all 0.22s;
        }
        .nav-btn-ghost {
            background: transparent; color: var(--gold);
            border: 1px solid rgba(240,165,0,0.3);
        }
        .nav-btn-ghost:hover { background: var(--gold-dim); }
        .nav-btn-gold {
            background: linear-gradient(135deg, var(--gold), #e09200);
            color: #0f0c08; font-weight: 700;
            box-shadow: 0 3px 14px var(--gold-glow);
        }
        .nav-btn-gold:hover { background: linear-gradient(135deg, var(--gold-lt), var(--gold)); transform: translateY(-1px); }

        /* ── CARD ── */
        @keyframes fadeUp { to { opacity:1; transform:translateY(0); } }

        .card {
            position: relative; z-index: 1;
            width: 100%; max-width: 540px;
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: 0 32px 80px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,180,60,0.04) inset;
            opacity: 0; transform: translateY(20px);
            animation: fadeUp 0.5s 0.05s ease forwards;
        }
        .card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(240,165,0,0.35), transparent);
            z-index: 2;
        }

        /* ── CARD HEAD ── */
        .card-head {
            padding: 32px 36px 26px;
            border-bottom: 1px solid var(--border);
            position: relative; overflow: hidden;
        }
        .card-head::after {
            content: '';
            position: absolute; top: -80px; right: -80px;
            width: 280px; height: 280px;
            background: radial-gradient(circle, rgba(240,130,0,0.13) 0%, transparent 65%);
            pointer-events: none;
        }

        .brand {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none; margin-bottom: 22px;
            position: relative; z-index: 1;
        }
        .brand-icon {
            width: 34px; height: 34px;
            background: linear-gradient(135deg, var(--gold), #e09000);
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            color: #0f0c08; font-size: 14px;
            box-shadow: 0 0 16px var(--gold-glow);
        }
        .brand-name { font-family: var(--font-head); font-weight: 700; font-size: 1.05rem; color: var(--text); }

        .head-title {
            font-family: var(--font-head);
            font-size: 2.2rem; font-weight: 800; letter-spacing: -0.01em;
            color: var(--text); line-height: 1.1;
            margin-bottom: 6px; position: relative; z-index: 1;
        }
        .head-title span { color: var(--gold); }
        .head-sub { font-size: 0.875rem; color: var(--text-muted); line-height: 1.5; position: relative; z-index: 1; }

        /* ── CARD BODY ── */
        .card-body { padding: 28px 36px 32px; }

        /* ── ALERTS ── */
        .alert {
            display: flex; align-items: flex-start; gap: 10px;
            border-radius: var(--radius-sm); padding: 12px 14px;
            font-size: 0.855rem; margin-bottom: 20px; line-height: 1.5;
        }
        .alert i { font-size: 0.875rem; margin-top: 2px; flex-shrink: 0; }
        .alert-error   { background: var(--red-dim);  border: 1px solid rgba(255,77,109,0.22);  color: #ff7090; }
        .alert-success { background: var(--teal-dim); border: 1px solid rgba(0,212,170,0.22);   color: var(--teal); }

        /* ── FORM ── */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .form-group { margin-bottom: 16px; }

        .form-label {
            display: block; margin-bottom: 7px;
            font-size: 0.72rem; font-weight: 700;
            letter-spacing: 0.1em; text-transform: uppercase;
            color: var(--text-muted);
        }

        .input-wrap { position: relative; }

        .input-icon {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: var(--text-dim); font-size: 0.82rem; pointer-events: none;
            transition: color 0.2s; z-index: 1;
        }
        .input-wrap:focus-within .input-icon { color: var(--gold); }

        .form-input {
            width: 100%;
            padding: 12px 40px 12px 40px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-warm);
            font-family: var(--font-body); font-size: 0.9rem;
            transition: all 0.22s; outline: none;
        }
        .form-input::placeholder { color: var(--text-dim); }
        .form-input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(240,165,0,0.1);
            background: var(--surface3);
        }
        .form-input.no-icon { padding-left: 14px; }

        .pw-eye {
            position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: var(--text-dim); font-size: 0.82rem;
            padding: 4px; transition: color 0.2s; z-index: 1;
        }
        .pw-eye:hover { color: var(--text-muted); }

        /* strength bar */
        .strength-wrap { margin-top: 7px; }
        .strength-bar-bg {
            height: 3px; background: var(--surface3);
            border-radius: 3px; overflow: hidden;
        }
        .strength-bar { height: 100%; width: 0; border-radius: 3px; transition: width 0.3s, background 0.3s; }
        .strength-lbl { font-size: 0.7rem; color: var(--text-dim); margin-top: 4px; }

        /* ── ROLE CARDS ── */
        .role-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 6px; }

        .role-opt { position: relative; }
        .role-opt input { position: absolute; opacity: 0; pointer-events: none; }

        .role-lbl {
            display: flex; flex-direction: column; align-items: center;
            gap: 9px; padding: 22px 14px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            cursor: pointer; text-align: center;
            transition: all 0.24s ease;
            position: relative; overflow: hidden;
        }
        .role-lbl::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 2px;
            opacity: 0; transition: opacity 0.24s;
        }
        .role-opt.rc-buyer  .role-lbl::before { background: linear-gradient(90deg, var(--blue), var(--teal)); }
        .role-opt.rc-seller .role-lbl::before { background: linear-gradient(90deg, var(--teal), var(--gold)); }

        .role-lbl:hover { border-color: var(--border2); transform: translateY(-2px); background: var(--surface3); }

        .role-opt input:checked + .role-lbl {
            border-color: var(--gold);
            background: rgba(240,165,0,0.07);
            box-shadow: 0 0 0 1px rgba(240,165,0,0.15);
        }
        .role-opt input:checked + .role-lbl::before { opacity: 1; }

        .role-badge {
            width: 46px; height: 46px; border-radius: 13px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.15rem; transition: all 0.22s;
            border: 1px solid transparent;
        }
        .rb-buyer  { background: var(--blue-dim); color: var(--blue);  border-color: rgba(78,159,255,0.15); }
        .rb-seller { background: var(--teal-dim); color: var(--teal);  border-color: rgba(0,212,170,0.15); }

        .role-name { font-family: var(--font-head); font-size: 1rem; font-weight: 700; color: var(--text); letter-spacing: -0.01em; }
        .role-desc-text { font-size: 0.75rem; color: var(--text-muted); }

        .role-check {
            width: 18px; height: 18px; border-radius: 50%;
            border: 1px solid var(--border2);
            background: var(--surface2);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.55rem; color: transparent;
            transition: all 0.22s;
        }
        .role-opt input:checked + .role-lbl .role-check {
            background: var(--gold); border-color: var(--gold); color: #0f0c08;
        }

        /* ── SUBMIT ── */
        .btn-submit {
            width: 100%; padding: 13px 20px; margin-top: 8px;
            background: linear-gradient(135deg, var(--gold), #e09200);
            color: #0f0c08;
            border: none; border-radius: var(--radius-sm);
            font-family: var(--font-body); font-size: 0.95rem; font-weight: 700;
            cursor: pointer; letter-spacing: 0.02em;
            box-shadow: 0 4px 20px var(--gold-glow), 0 1px 0 rgba(255,255,255,0.1) inset;
            transition: all 0.24s ease;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-submit:hover {
            background: linear-gradient(135deg, var(--gold-lt), var(--gold));
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(240,165,0,0.4);
        }
        .btn-submit:active { transform: translateY(0); }

        /* ── DIVIDER ── */
        .divider {
            display: flex; align-items: center; gap: 12px;
            margin: 20px 0; color: var(--text-dim); font-size: 0.78rem;
            letter-spacing: 0.05em;
        }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        /* ── SOCIAL ── */
        .socials { display: flex; gap: 10px; margin-bottom: 22px; }
        .social-btn {
            flex: 1; padding: 10px 8px;
            background: var(--surface2); border: 1px solid var(--border);
            border-radius: var(--radius-sm); color: var(--text-muted);
            font-size: 0.875rem; font-family: var(--font-body);
            cursor: pointer; text-decoration: none;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: all 0.22s;
        }
        .social-btn:hover { border-color: var(--border2); color: var(--text-warm); background: var(--surface3); transform: translateY(-2px); }


        .footer-brand-icon {
    width: 34px;
    height: 34px;
    background: linear-gradient(135deg, var(--gold), #e09000);
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 14px var(--gold-glow2);
}
.footer-brand-icon img {
    width: 80%;
    height: 80%;
    object-fit: contain;
}
        /* ── FOOTER ── */
        .card-foot { text-align: center; font-size: 0.875rem; color: var(--text-muted); }
        .card-foot a { color: var(--gold); text-decoration: none; font-weight: 600; transition: color 0.2s; }
        .card-foot a:hover { color: var(--gold-lt); }

        /* ── TRUST ── */
        .trust-strip {
            display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;
            margin-top: 18px; padding-top: 18px;
            border-top: 1px solid var(--border);
        }
        .trust-item { display: flex; align-items: center; gap: 6px; font-size: 0.75rem; color: var(--text-dim); }
        .trust-item i { font-size: 0.68rem; color: var(--teal); }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--surface3); border-radius: 3px; }

        @media (max-width: 540px) {
            .card-head, .card-body { padding-left: 22px; padding-right: 22px; }
            .form-row  { grid-template-columns: 1fr; }
            .role-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="bg-glow1"></div>
    <div class="bg-glow2"></div>
    <div class="bg-glow3"></div>
    <div class="bg-grid"></div>

    <!-- NAV -->
    <nav>
        <div class="nav-inner">
            <a href="index.php" class="nav-logo">
    <div class="nav-logo-icon">
        <img src="images/logoblack.png" alt="Trusted Midman">
    </div>
    <span class="nav-logo-text">Trusted Midman</span>
</a>
            <div class="nav-links">
                <a href="index.php#how"      class="nav-link">How It Works</a>
                <a href="index.php#features" class="nav-link">Features</a>
                <a href="products.php"       class="nav-link">Marketplace</a>
                <a href="login.php"    class="nav-btn nav-btn-ghost" style="margin-left:8px;">Sign In</a>
                <a href="register.php" class="nav-btn nav-btn-gold"  style="margin-left:4px;">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- CARD -->
    <div class="card">

        <!-- HEAD -->
        <div class="card-head">
            <a href="index.php" class="nav-logo">
    <div class="nav-logo-icon">
        <img src="images/logoblack.png" alt="Trusted Midman">
    </div>
    <span class="nav-logo-text">Trusted Midman</span>
</a>
            <h1 class="head-title">Create your <span>account.</span></h1>
            <p class="head-sub">Join thousands of gamers trading securely every day.</p>
        </div>

        <!-- BODY -->
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
                    <?php if(strpos($success, 'Account created') !== false): ?>
                        <a href="login.php" style="color:var(--teal);font-weight:600;margin-left:6px;">Sign in →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="regForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <!-- Username + Email -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <div class="input-wrap">
                            <i class="input-icon fas fa-user"></i>
                            <input type="text" id="username" name="username" class="form-input"
                                   placeholder="gamertag" autocomplete="username"
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <div class="input-wrap">
                            <i class="input-icon fas fa-envelope"></i>
                            <input type="email" id="email" name="email" class="form-input"
                                   placeholder="you@example.com" autocomplete="email"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Full Name -->
                <div class="form-group">
                    <label class="form-label" for="full_name">Full Name</label>
                    <div class="input-wrap">
                        <i class="input-icon fas fa-id-card"></i>
                        <input type="text" id="full_name" name="full_name" class="form-input"
                               placeholder="John Doe" autocomplete="name"
                               value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" required>
                    </div>
                </div>

                <!-- Phone -->
                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number</label>
                    <div class="input-wrap">
                        <i class="input-icon fas fa-phone"></i>
                        <input type="tel" id="phone" name="phone" class="form-input"
                               placeholder="+1 234 567 8900" autocomplete="tel"
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrap">
                        <i class="input-icon fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-input"
                               placeholder="Create a strong password" autocomplete="new-password" required>
                        <button type="button" class="pw-eye" onclick="togglePw()">
                            <i class="fas fa-eye" id="pwEyeIcon"></i>
                        </button>
                    </div>
                    <div class="strength-wrap">
                        <div class="strength-bar-bg"><div class="strength-bar" id="sBar"></div></div>
                        <div class="strength-lbl" id="sLbl"></div>
                    </div>
                </div>

                <!-- Role -->
                <div class="form-group">
                    <label class="form-label">Register as</label>
                    <div class="role-grid">
                        <div class="role-opt rc-buyer">
                            <input type="radio" id="role_buyer" name="role" value="buyer"
                                   <?php echo (!isset($_POST['role']) || $_POST['role']=='buyer') ? 'checked' : ''; ?>>
                            <label for="role_buyer" class="role-lbl">
                                <div class="role-badge rb-buyer"><i class="fas fa-bag-shopping"></i></div>
                                <div class="role-name">Buyer</div>
                                <div class="role-desc-text">Browse &amp; purchase items</div>
                                <div class="role-check"><i class="fas fa-check"></i></div>
                            </label>
                        </div>
                        <div class="role-opt rc-seller">
                            <input type="radio" id="role_seller" name="role" value="seller"
                                   <?php echo (isset($_POST['role']) && $_POST['role']=='seller') ? 'checked' : ''; ?>>
                            <label for="role_seller" class="role-lbl">
                                <div class="role-badge rb-seller"><i class="fas fa-store"></i></div>
                                <div class="role-name">Seller</div>
                                <div class="role-desc-text">List &amp; sell your items</div>
                                <div class="role-check"><i class="fas fa-check"></i></div>
                            </label>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>

            <div class="divider">or sign up with</div>
            <div class="card-foot">
                Already have an account? <a href="login.php">Sign in here</a>
            </div>

            <div class="trust-strip">
                <div class="trust-item"><i class="fas fa-shield-halved"></i> Escrow Protected</div>
                <div class="trust-item"><i class="fas fa-lock"></i> Secure Signup</div>
                <div class="trust-item"><i class="fas fa-circle-check"></i> Verified Platform</div>
            </div>
        </div>
    </div>

    <script>
        function togglePw() {
            const inp  = document.getElementById('password');
            const icon = document.getElementById('pwEyeIcon');
            const hide = inp.type === 'password';
            inp.type = hide ? 'text' : 'password';
            icon.className = hide ? 'fas fa-eye-slash' : 'fas fa-eye';
        }

        const levels = [
            { max:0,  w:'0%',    bg:'var(--red)',    txt:'' },
            { max:1,  w:'25%',   bg:'var(--red)',    txt:'Weak' },
            { max:2,  w:'50%',   bg:'var(--orange)', txt:'Fair' },
            { max:3,  w:'75%',   bg:'var(--gold)',   txt:'Good' },
            { max:99, w:'100%',  bg:'var(--teal)',   txt:'Strong' },
        ];

        document.getElementById('password').addEventListener('input', function() {
            const v = this.value;
            let score = 0;
            if(v.length >= 8)          score++;
            if(/[A-Z]/.test(v))        score++;
            if(/[0-9]/.test(v))        score++;
            if(/[^A-Za-z0-9]/.test(v)) score++;
            const l = levels.find(x => score <= x.max) || levels[levels.length-1];
            const bar = document.getElementById('sBar');
            const lbl = document.getElementById('sLbl');
            bar.style.width      = v ? l.w  : '0%';
            bar.style.background = l.bg;
            lbl.textContent      = v ? l.txt : '';
            lbl.style.color      = l.bg;
        });
    </script>
</body>
</html>
