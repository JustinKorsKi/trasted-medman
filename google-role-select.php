<?php
require_once 'includes/config.php';

if(!isset($_SESSION['google_data'])) {
    header('Location: login.php');
    exit();
}

$google_data = $_SESSION['google_data'];
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $role = mysqli_real_escape_string($conn, $_POST['role']);

    if($role != 'buyer' && $role != 'seller') {
        $error = 'Please select a valid role.';
    } else {
        $username = explode('@', $google_data['email'])[0];
        $base_username = $username;
        $counter = 1;
        while(mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'"))) {
            $username = $base_username . $counter;
            $counter++;
        }

        $random_password = bin2hex(random_bytes(16));
        $hashed_password = password_hash($random_password, PASSWORD_BCRYPT);

        $query = "INSERT INTO users (
            username, email, password, full_name, role,
            google_id, avatar, email_verified, is_active, created_at
        ) VALUES (
            '$username', '{$google_data['email']}', '$hashed_password', '{$google_data['name']}', '$role',
            '{$google_data['id']}', '{$google_data['picture']}', 1, 1, NOW()
        )";

        if(mysqli_query($conn, $query)) {
            $user_id = mysqli_insert_id($conn);
            $_SESSION['user_id']   = $user_id;
            $_SESSION['username']  = $username;
            $_SESSION['role']      = $role;
            $_SESSION['full_name'] = $google_data['name'];
            unset($_SESSION['google_data']);
            $_SESSION['flash'] = ['message' => 'Welcome! Your account has been created via Google.', 'type' => 'success'];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Failed to create account. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Your Role — Trusted Midman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0f0c08;
            --surface:   #1a1510;
            --surface2:  #201a13;
            --surface3:  #271f16;
            --border:    rgba(255,180,80,0.08);
            --border2:   rgba(255,180,80,0.15);
            --admin:     #e03535;
            --admin-lt:  #ff5a5a;
            --admin-dim: rgba(224,53,53,0.12);
            --admin-glow:rgba(224,53,53,0.28);
            --teal:      #00d4aa;
            --teal-dim:  rgba(0,212,170,0.11);
            --blue:      #4e9fff;
            --blue-dim:  rgba(78,159,255,0.12);
            --red:       #ff4d6d;
            --red-dim:   rgba(255,77,109,0.12);
            --gold:      #f0a500;
            --gold-dim:  rgba(240,165,0,0.13);
            --text:      #ffffff;
            --text-warm: #f0e8da;
            --text-muted:#a89880;
            --text-dim:  #5a4e3a;
            --r:         12px;
            --r-lg:      20px;
            --fh:        'Barlow Condensed', sans-serif;
            --fb:        'DM Sans', sans-serif;
        }

        html, body {
            min-height: 100vh;
            font-family: var(--fb);
            background: var(--bg);
            color: var(--text-warm);
            -webkit-font-smoothing: antialiased;
        }

        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 32px 20px;
            position: relative;
            overflow: hidden;
        }

        /* Ambient glows */
        body::before {
            content: '';
            position: fixed; top: -150px; left: 50%;
            transform: translateX(-50%);
            width: 700px; height: 500px;
            background: radial-gradient(ellipse, rgba(224,53,53,0.07) 0%, transparent 65%);
            pointer-events: none; z-index: 0;
        }
        body::after {
            content: '';
            position: fixed; bottom: -150px; right: -100px;
            width: 500px; height: 500px;
            background: radial-gradient(ellipse, rgba(224,53,53,0.04) 0%, transparent 65%);
            pointer-events: none; z-index: 0;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        /* ── WORDMARK ── */
        .wordmark {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 28px; position: relative; z-index: 1;
            animation: fadeUp 0.38s ease both;
        }
        .wm-icon {
            width: 32px; height: 32px;
            background: linear-gradient(135deg, var(--admin), #b01e1e);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 14px;
            box-shadow: 0 0 16px var(--admin-glow);
        }
        .wm-label {
            font-family: var(--fh);
            font-size: 1rem; font-weight: 700;
            color: var(--text); letter-spacing: -0.01em;
        }
        .wm-label span {
            display: block; font-family: var(--fb);
            font-size: 0.55rem; font-weight: 600;
            color: var(--admin); text-transform: uppercase; letter-spacing: 0.14em;
        }

        /* ── CARD ── */
        .card {
            width: 100%; max-width: 480px;
            background: var(--surface);
            border: 1px solid rgba(224,53,53,0.2);
            border-radius: var(--r-lg);
            overflow: hidden;
            position: relative; z-index: 1;
            animation: fadeUp 0.44s ease 0.06s both;
            box-shadow: 0 32px 80px rgba(0,0,0,0.5), 0 0 0 1px rgba(224,53,53,0.06);
        }

        /* Red gradient top bar */
        .card-bar {
            height: 3px;
            background: linear-gradient(90deg, #b01e1e 0%, var(--admin) 50%, var(--admin-lt) 100%);
        }

        /* ── CARD HEADER ── */
        .card-head {
            padding: 28px 32px 24px;
            border-bottom: 1px solid var(--border);
            position: relative; overflow: hidden;
        }
        .card-head::after {
            content: '';
            position: absolute; top: -40px; right: -40px;
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(224,53,53,0.10) 0%, transparent 65%);
            pointer-events: none;
        }

        .head-icon {
            width: 52px; height: 52px; border-radius: 14px;
            background: var(--admin-dim);
            border: 1px solid rgba(224,53,53,0.22);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; color: var(--admin);
            margin-bottom: 16px; position: relative; z-index: 1;
        }
        .card-title {
            font-family: var(--fh);
            font-size: 1.6rem; font-weight: 800;
            color: var(--text); letter-spacing: -0.01em;
            margin-bottom: 5px; position: relative; z-index: 1;
        }
        .card-title span { color: var(--admin); }
        .card-sub {
            font-size: 0.84rem; color: var(--text-muted);
            position: relative; z-index: 1;
        }

        /* ── CARD BODY ── */
        .card-body { padding: 24px 32px 28px; }

        /* ── ALERT ── */
        .alert {
            display: flex; align-items: flex-start; gap: 9px;
            background: var(--red-dim); border: 1px solid rgba(255,77,109,0.2);
            border-radius: var(--r); padding: 12px 14px;
            margin-bottom: 20px; font-size: 0.84rem; color: #ff7090; line-height: 1.55;
        }
        .alert i { margin-top: 1px; flex-shrink: 0; }

        /* ── USER CHIP ── */
        .user-chip {
            display: flex; align-items: center; gap: 13px;
            background: var(--surface2); border: 1px solid var(--border2);
            border-radius: var(--r); padding: 14px 16px;
            margin-bottom: 24px; position: relative; overflow: hidden;
        }
        .user-chip::before {
            content: '';
            position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
            background: var(--admin);
        }
        .user-avatar {
            width: 46px; height: 46px; border-radius: 50%;
            overflow: hidden; flex-shrink: 0;
            border: 2px solid rgba(224,53,53,0.25);
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-avatar-fallback {
            width: 46px; height: 46px; border-radius: 50%;
            background: linear-gradient(135deg, var(--admin), #8b1a1a);
            display: flex; align-items: center; justify-content: center;
            font-family: var(--fh); font-weight: 700; font-size: 1rem;
            color: white; flex-shrink: 0;
        }
        .user-info { flex: 1; min-width: 0; }
        .user-name {
            font-size: 0.9rem; font-weight: 600; color: var(--text-warm);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .user-email {
            font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .google-badge {
            display: flex; align-items: center; gap: 5px;
            font-size: 0.62rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.08em; color: var(--admin);
            background: var(--admin-dim); border: 1px solid rgba(224,53,53,0.2);
            padding: 3px 8px; border-radius: 20px; flex-shrink: 0;
        }

        /* ── ROLE SECTION LABEL ── */
        .section-label {
            font-size: 0.68rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.12em; color: var(--text-muted);
            margin-bottom: 12px;
        }

        /* ── ROLE GRID ── */
        .role-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 22px; }

        .role-option { position: relative; }
        .role-option input { position: absolute; opacity: 0; pointer-events: none; }

        .role-card {
            display: flex; flex-direction: column; align-items: center;
            gap: 10px; padding: 20px 16px;
            background: var(--surface2); border: 1px solid var(--border);
            border-radius: var(--r); cursor: pointer;
            transition: all 0.22s; text-align: center;
            position: relative; overflow: hidden;
        }
        .role-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 2px;
            background: transparent; transition: background 0.22s;
        }
        .role-card:hover { border-color: var(--border2); transform: translateY(-2px); }

        /* buyer = blue */
        .role-option:first-child .role-card:hover::before { background: var(--blue); }
        .role-option:first-child input:checked + .role-card {
            border-color: var(--blue); background: var(--blue-dim);
        }
        .role-option:first-child input:checked + .role-card::before { background: var(--blue); }

        /* seller = teal */
        .role-option:last-child .role-card:hover::before { background: var(--teal); }
        .role-option:last-child input:checked + .role-card {
            border-color: var(--teal); background: var(--teal-dim);
        }
        .role-option:last-child input:checked + .role-card::before { background: var(--teal); }

        .role-icon {
            width: 48px; height: 48px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; transition: all 0.22s;
        }
        .role-icon.buyer  { background: var(--blue-dim); color: var(--blue); border: 1px solid rgba(78,159,255,0.2); }
        .role-icon.seller { background: var(--teal-dim); color: var(--teal); border: 1px solid rgba(0,212,170,0.2); }

        .role-option input:checked + .role-card .role-icon.buyer  { background: var(--blue); color: white; }
        .role-option input:checked + .role-card .role-icon.seller { background: var(--teal); color: #0f0c08; }

        .role-title {
            font-family: var(--fh); font-size: 1rem; font-weight: 700;
            color: var(--text); letter-spacing: -0.01em;
        }
        .role-desc { font-size: 0.75rem; color: var(--text-muted); line-height: 1.5; }

        /* ── SUBMIT BUTTON ── */
        .btn-submit {
            width: 100%; padding: 13px;
            background: linear-gradient(135deg, var(--admin), #b01e1e);
            border: none; border-radius: var(--r);
            color: white; font-family: var(--fb);
            font-size: 0.9rem; font-weight: 700; letter-spacing: 0.01em;
            cursor: pointer; transition: all 0.22s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            box-shadow: 0 4px 18px var(--admin-glow);
        }
        .btn-submit:hover { opacity: 0.88; transform: translateY(-1px); box-shadow: 0 8px 26px rgba(224,53,53,0.4); }
        .btn-submit:active { transform: translateY(0); }

        /* ── FOOTER ── */
        .card-footer {
            padding: 14px 32px 18px;
            border-top: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .footer-muted { font-size: 0.78rem; color: var(--text-dim); }
        .footer-link {
            font-size: 0.78rem; color: var(--admin); text-decoration: none;
            font-weight: 500; transition: opacity 0.18s;
        }
        .footer-link:hover { opacity: 0.75; }

        @media(max-width:520px) {
            .card-head, .card-body { padding-left: 20px; padding-right: 20px; }
            .card-footer { padding-left: 20px; padding-right: 20px; }
            .role-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- Wordmark -->
<div class="wordmark">
    <div class="wm-icon"><i class="fas fa-shield-halved"></i></div>
    <div class="wm-label">Trusted Midman <span>Secure Registration</span></div>
</div>

<!-- Card -->
<div class="card">
    <div class="card-bar"></div>

    <div class="card-head">
        <div class="head-icon"><i class="fas fa-user-tag"></i></div>
        <div class="card-title">Choose your <span>role</span></div>
        <div class="card-sub">How would you like to use Trusted Midman?</div>
    </div>

    <div class="card-body">

        <?php if($error): ?>
        <div class="alert">
            <i class="fas fa-circle-exclamation"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- User chip -->
        <div class="user-chip">
            <?php if(!empty($google_data['picture'])): ?>
                <div class="user-avatar"><img src="<?php echo htmlspecialchars($google_data['picture']); ?>" alt=""></div>
            <?php else: ?>
                <div class="user-avatar-fallback"><?php echo strtoupper(substr($google_data['name'],0,2)); ?></div>
            <?php endif; ?>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($google_data['name']); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($google_data['email']); ?></div>
            </div>
            <div class="google-badge"><i class="fab fa-google" style="font-size:0.7rem;"></i> Google</div>
        </div>

        <!-- Role selection -->
        <div class="section-label">Select your account type</div>

        <form method="POST" action="">
            <div class="role-grid">
                <div class="role-option">
                    <input type="radio" name="role" id="role_buyer" value="buyer" required>
                    <label for="role_buyer" class="role-card">
                        <div class="role-icon buyer"><i class="fas fa-bag-shopping"></i></div>
                        <div class="role-title">Buyer</div>
                        <div class="role-desc">Browse and purchase gaming items securely</div>
                    </label>
                </div>
                <div class="role-option">
                    <input type="radio" name="role" id="role_seller" value="seller" required>
                    <label for="role_seller" class="role-card">
                        <div class="role-icon seller"><i class="fas fa-store"></i></div>
                        <div class="role-title">Seller</div>
                        <div class="role-desc">List and sell your gaming items</div>
                    </label>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-arrow-right-to-bracket"></i> Continue with Selected Role
            </button>
        </form>

    </div>

    <div class="card-footer">
        <span class="footer-muted">Wrong account?</span>
        <a href="login.php" class="footer-link"><i class="fas fa-arrow-left" style="font-size:0.6rem;"></i> Back to login</a>
    </div>
</div>

</body>
</html>