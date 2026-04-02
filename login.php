<?php
require_once 'includes/config.php';

if(isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$lock_message = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }

    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);
    $ip = $_SERVER['REMOTE_ADDR'];

    if (isUserLocked($username)) {
        $lock_message = 'Account is temporarily locked. Please try again later.';
    } else {
        if (checkLoginAttempts($username, $ip)) {
            $lock_message = 'Too many failed attempts. Account locked for ' . LOCKOUT_TIME . ' minutes.';
        } else {
            $query = "SELECT * FROM users WHERE (username='$username' OR email='$username')";
            $result = mysqli_query($conn, $query);

            if(mysqli_num_rows($result) == 1) {
                $user = mysqli_fetch_assoc($result);

                if(password_verify($password, $user['password'])) {
                    if(!$user['email_verified']) {
                        $error = 'Please verify your email before logging in.';
                        recordLoginAttempt($username, $ip, false);
                    } elseif(isset($user['is_active']) && $user['is_active'] == 0) {
                        $error = 'Your account has been suspended.';
                        recordLoginAttempt($username, $ip, false);
                    } else {
                        recordLoginAttempt($username, $ip, true);
                        if($user['two_factor_enabled']) {
                            $_SESSION['2fa_user_id'] = $user['id'];
                            $_SESSION['2fa_remember'] = $remember;
                            header('Location: verify-2fa.php');
                            exit();
                        }
                        $_SESSION['user_id']   = $user['id'];
                        $_SESSION['username']  = $user['username'];
                        $_SESSION['role']      = $user['role'];
                        $_SESSION['full_name'] = $user['full_name'];
                        mysqli_query($conn, "UPDATE users SET last_login = NOW(), last_login_ip = '$ip' WHERE id = {$user['id']}");
                        if ($remember) {
                            $token = bin2hex(random_bytes(32));
                            $expires = date('Y-m-d H:i:s', strtotime('+' . REMEMBER_ME_DAYS . ' days'));
                            mysqli_query($conn, "INSERT INTO user_sessions (user_id, token, expires_at) VALUES ({$user['id']}, '$token', '$expires')");
                            setcookie('remember_token', $token, time() + (REMEMBER_ME_DAYS * 24 * 60 * 60), '/', '', false, true);
                        }
                        header('Location: dashboard.php');
                        exit();
                    }
                } else {
                    recordLoginAttempt($username, $ip, false);
                    if (checkLoginAttempts($username, $ip)) {
                        $lock_until = date('Y-m-d H:i:s', strtotime('+' . LOCKOUT_TIME . ' minutes'));
                        mysqli_query($conn, "UPDATE users SET locked_until = '$lock_until' WHERE email = '$username' OR username = '$username'");
                        $error = 'Too many failed attempts. Account locked for ' . LOCKOUT_TIME . ' minutes.';
                    } else {
                        $error = 'Invalid username / email or password.';
                    }
                }
            } else {
                recordLoginAttempt($username, $ip, false);
                $error = 'Invalid username / email or password.';
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
    <title>Sign In — Trusted Midman</title>
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
            --surface2:   #201a13;
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
            --text:       #ffffff;
            --text-warm:  #fff7e8;
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
            padding: 80px 24px 40px;
            position: relative;
            overflow: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ── BACKGROUND ── */
        .bg-glow1 {
            position: fixed; top: -160px; left: -160px;
            width: 640px; height: 640px;
            background: radial-gradient(circle, rgba(255,140,20,0.2) 0%, rgba(200,80,0,0.08) 40%, transparent 65%);
            pointer-events: none; z-index: 0;
        }
        .bg-glow2 {
            position: fixed; bottom: -140px; right: -120px;
            width: 560px; height: 560px;
            background: radial-gradient(circle, rgba(180,70,0,0.15) 0%, rgba(240,130,0,0.06) 45%, transparent 65%);
            pointer-events: none; z-index: 0;
        }
        .bg-glow3 {
            position: fixed; top: 40%; left: 50%; transform: translateX(-50%);
            width: 800px; height: 300px;
            background: radial-gradient(ellipse, rgba(240,165,0,0.05) 0%, transparent 65%);
            pointer-events: none; z-index: 0;
        }
        .bg-grid {
            position: fixed; inset: 0;
            background-image:
                linear-gradient(rgba(255,180,60,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,180,60,0.03) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 10%, transparent 100%);
            pointer-events: none; z-index: 0;
        }

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

        
        /* ── NAV ── */
        nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 200;
            height: 64px;
            display: flex; align-items: center;
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
            text-decoration: none; cursor: pointer; border: none; transition: all 0.22s ease;
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
            width: 100%; max-width: 460px;
            background: var(--surface);
            border: 1px solid var(--border2);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: 0 32px 80px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,180,60,0.04) inset;
            opacity: 0; transform: translateY(20px);
            animation: fadeUp 0.5s 0.05s ease forwards;
        }

        /* glossy top line */
        .card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 1px;
            background: linear-gradient(90deg, transparent, rgba(240,165,0,0.35), transparent);
            z-index: 2;
        }

        /* ── CARD HEADER ── */
        .card-head {
            padding: 32px 36px 26px;
            border-bottom: 1px solid var(--border);
            position: relative; overflow: hidden;
        }
        .card-head::after {
            content: '';
            position: absolute; top: -80px; right: -80px;
            width: 260px; height: 260px;
            background: radial-gradient(circle, rgba(240,130,0,0.14) 0%, transparent 65%);
            pointer-events: none;
        }

        .brand {
            display: flex; align-items: center; gap: 10px;
            text-decoration: none; margin-bottom: 24px;
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
            margin-bottom: 6px;
            position: relative; z-index: 1;
        }
        .head-title span { color: var(--gold); }

        .head-sub {
            font-size: 0.875rem; color: var(--text-muted); line-height: 1.5;
            position: relative; z-index: 1;
        }

        /* ── CARD BODY ── */
        .card-body { padding: 28px 36px 32px; }

        /* ── ALERTS ── */
        .alert {
            display: flex; align-items: flex-start; gap: 10px;
            border-radius: var(--radius-sm);
            padding: 12px 14px; font-size: 0.855rem;
            margin-bottom: 20px; line-height: 1.5;
        }
        .alert-error {
            background: var(--red-dim); border: 1px solid rgba(255,77,109,0.22);
            color: #ff7090;
        }
        .alert-warning {
            background: rgba(240,165,0,0.1); border: 1px solid rgba(240,165,0,0.22);
            color: var(--gold);
        }
        .alert i { font-size: 0.875rem; margin-top: 2px; flex-shrink: 0; }

        /* ── FORM ── */
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

        .form-input {
            width: 100%;
            padding: 12px 42px 12px 40px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-warm);
            font-family: var(--font-body); font-size: 0.9rem;
            transition: all 0.22s;
            outline: none;
        }
        .form-input::placeholder { color: var(--text-dim); }
        .form-input:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(240,165,0,0.1);
            background: var(--surface3);
        }
        .input-wrap:focus-within .input-icon { color: var(--gold); }

        .pw-eye {
            position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer;
            color: var(--text-dim); font-size: 0.82rem; padding: 4px;
            transition: color 0.2s; z-index: 1;
        }
        .pw-eye:hover { color: var(--text-muted); }

        /* ── ROW ── */
        .row-between {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 22px; flex-wrap: wrap; gap: 8px;
        }

        .check-label {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.84rem; color: var(--text-muted);
            cursor: pointer; user-select: none;
        }
        .check-label input { display: none; }
        .check-box {
            width: 17px; height: 17px; border-radius: 5px;
            border: 1px solid var(--border2);
            background: var(--surface2);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; transition: all 0.2s;
        }
        .check-label input:checked + .check-box {
            background: var(--gold); border-color: var(--gold);
        }
        .check-box i { font-size: 0.6rem; color: #0f0c08; display: none; }
        .check-label input:checked + .check-box i { display: block; }

        .forgot {
            font-size: 0.84rem; color: var(--text-muted);
            text-decoration: none; transition: color 0.2s;
        }
        .forgot:hover { color: var(--gold); }

        /* ── SUBMIT ── */
        .btn-submit {
            width: 100%; padding: 13px 20px;
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
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px; background: var(--border);
        }

        /* ── SOCIAL BTNS ── */
        .socials { display: flex; gap: 10px; margin-bottom: 22px; }

        .social-btn {
            flex: 1; padding: 10px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-muted); font-size: 1rem;
            cursor: pointer; text-decoration: none;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            font-size: 0.875rem; font-family: var(--font-body);
            transition: all 0.22s;
        }
        .social-btn:hover {
            border-color: var(--border2); color: var(--text-warm);
            background: var(--surface3); transform: translateY(-2px);
        }

        /* ── FOOTER ── */
        .card-foot {
            text-align: center; font-size: 0.875rem; color: var(--text-muted);
        }
        .card-foot a {
            color: var(--gold); text-decoration: none; font-weight: 600;
            transition: color 0.2s;
        }
        .card-foot a:hover { color: var(--gold-lt); }

        /* ── TRUST STRIP ── */
        .trust-strip {
            display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;
            margin-top: 18px; padding-top: 18px;
            border-top: 1px solid var(--border);
        }
        .trust-item {
            display: flex; align-items: center; gap: 6px;
            font-size: 0.75rem; color: var(--text-dim);
        }
        .trust-item i { font-size: 0.68rem; color: var(--teal); }

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: var(--bg); }
        ::-webkit-scrollbar-thumb { background: var(--surface3); border-radius: 3px; }
    </style>
</head>
<body>

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

    <!-- BG -->
    <div class="bg-glow1"></div>
    <div class="bg-glow2"></div>
    <div class="bg-glow3"></div>
    <div class="bg-grid"></div>

    <!-- CARD -->
    <div class="card">

        <!-- HEADER -->
        <div class="card-head">
            <a href="index.php" class="brand">
                <div class="brand-icon"><i class="fas fa-shield-halved"></i></div>
                <span class="brand-name">Trusted Midman</span>
            </a>
            <h1 class="head-title">Welcome <span>back.</span></h1>
            <p class="head-sub">Sign in to your account to continue trading securely.</p>
        </div>

        <!-- BODY -->
        <div class="card-body">

            <?php if($lock_message): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-clock"></i>
                    <?php echo htmlspecialchars($lock_message); ?>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group">
                    <label class="form-label" for="username">Email or Username</label>
                    <div class="input-wrap">
                        <i class="input-icon fas fa-user"></i>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            class="form-input"
                            placeholder="you@example.com"
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                            autocomplete="username"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrap">
                        <i class="input-icon fas fa-lock"></i>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="pw-eye" onclick="togglePw()" id="pwEye">
                            <i class="fas fa-eye" id="pwEyeIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="row-between">
                    <label class="check-label">
                        <input type="checkbox" name="remember">
                        <span class="check-box"><i class="fas fa-check"></i></span>
                        Remember me for 30 days
                    </label>
                    <a href="forgot-password.php" class="forgot">Forgot password?</a>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-arrow-right-to-bracket"></i> Sign In
                </button>
            </form>

            <div class="divider">or continue with</div>

            <div class="socials">
                <a href="google-login.php"   class="social-btn"><i class="fab fa-google"></i> Google</a>
                <a href="facebook-login.php" class="social-btn"><i class="fab fa-facebook-f"></i> Facebook</a>
                <a href="#"                  class="social-btn"><i class="fab fa-discord"></i> Discord</a>
            </div>

            <div class="card-foot">
                Don't have an account? <a href="register.php">Create one free</a>
            </div>

            <div class="trust-strip">
                <div class="trust-item"><i class="fas fa-shield-halved"></i> Escrow Protected</div>
                <div class="trust-item"><i class="fas fa-lock"></i> Secure Login</div>
                <div class="trust-item"><i class="fas fa-circle-check"></i> Verified Platform</div>
            </div>
        </div>
    </div>

    <script>
        function togglePw() {
            const input = document.getElementById('password');
            const icon  = document.getElementById('pwEyeIcon');
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            icon.className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
        }
    </script>
</body>
</html>