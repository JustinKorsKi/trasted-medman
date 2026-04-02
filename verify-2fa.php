<?php
require_once 'includes/config.php';
require_once 'includes/2fa-functions.php';

if(!isset($_SESSION['2fa_user_id'])) {
    header('Location: login.php'); exit();
}

$user_id  = $_SESSION['2fa_user_id'];
$remember = $_SESSION['2fa_remember'] ?? false;
$error    = '';

$query    = mysqli_query($conn, "SELECT username FROM users WHERE id = $user_id");
$user     = mysqli_fetch_assoc($query);
$username = $user['username'];

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code      = $_POST['code'];
    $is_backup = isset($_POST['use_backup']);
    $verified  = $is_backup ? verifyBackupCode($user_id, $code) : verify2FACode($user_id, $code);

    if($verified) {
        $query = mysqli_query($conn, "SELECT * FROM users WHERE id = $user_id");
        $user  = mysqli_fetch_assoc($query);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        if($remember) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
            mysqli_query($conn,"INSERT INTO user_sessions (user_id, token, expires_at) VALUES ($user_id, '$token', '$expires')");
            setcookie('remember_token', $token, time() + (30*24*60*60), '/', '', false, true);
        }
        unset($_SESSION['2fa_user_id'], $_SESSION['2fa_remember']);
        header('Location: dashboard.php'); exit();
    } else {
        $error = $is_backup ? 'Invalid backup code. Please try again.' : 'Invalid verification code. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication — Trusted Midman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0d0b09;
            --surface:   #161210;
            --surface2:  #1e1814;
            --surface3:  #252019;
            --border:    rgba(255,180,80,0.07);
            --border2:   rgba(255,180,80,0.13);
            --p:         #a064ff;
            --p-lt:      #c090ff;
            --p-dk:      #7040cc;
            --p-dim:     rgba(160,100,255,0.11);
            --p-border:  rgba(160,100,255,0.22);
            --p-glow:    rgba(160,100,255,0.22);
            --red:       #ff4d6d;
            --red-dim:   rgba(255,77,109,0.10);
            --teal:      #00d4aa;
            --text:      #ffffff;
            --text-warm: #ede4d4;
            --text-muted:#9e8e78;
            --text-dim:  #524638;
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
            padding: 40px 20px;
        }

        /* Soft ambient glow behind everything */
        body::before {
            content: '';
            position: fixed;
            top: -150px; left: 50%;
            transform: translateX(-50%);
            width: 640px; height: 420px;
            background: radial-gradient(ellipse, rgba(160,100,255,0.07) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes breathe {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.6; }
        }

        /* ── WORDMARK ── */
        .wordmark {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 28px;
            position: relative;
            z-index: 1;
            animation: fadeUp 0.38s ease both;
        }
        .wm-icon {
            width: 30px; height: 30px;
            background: linear-gradient(135deg, var(--p), var(--p-dk));
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 13px;
        }
        .wm-label {
            font-family: var(--fh);
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text);
            letter-spacing: -0.01em;
        }
        .wm-label span {
            display: block;
            font-family: var(--fb);
            font-size: 0.55rem;
            font-weight: 600;
            color: var(--p);
            text-transform: uppercase;
            letter-spacing: 0.14em;
        }

        /* ── CARD ── */
        .card {
            width: 100%;
            max-width: 400px;
            background: var(--surface);
            border: 1px solid var(--p-border);
            border-radius: var(--r-lg);
            overflow: hidden;
            position: relative;
            z-index: 1;
            animation: fadeUp 0.44s ease 0.06s both;
        }

        /* Purple gradient top bar */
        .card-bar {
            height: 3px;
            background: linear-gradient(90deg, var(--p-dk) 0%, var(--p) 50%, var(--p-lt) 100%);
        }

        /* ── CARD BODY ── */
        .card-body {
            padding: 32px 32px 28px;
        }

        /* ── ICON HEADER ── */
        .icon-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
            margin-bottom: 28px;
        }
        .icon-ring {
            width: 62px; height: 62px;
            border-radius: 50%;
            background: var(--p-dim);
            border: 1px solid var(--p-border);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            color: var(--p);
            position: relative;
        }
        /* Teal active indicator dot */
        .icon-ring::after {
            content: '';
            position: absolute;
            bottom: 3px; right: 3px;
            width: 13px; height: 13px;
            background: var(--teal);
            border-radius: 50%;
            border: 2px solid var(--surface);
            animation: breathe 2.2s ease infinite;
        }
        .icon-title {
            font-family: var(--fh);
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.01em;
            text-align: center;
        }
        .icon-sub {
            font-size: 0.83rem;
            color: var(--text-muted);
            text-align: center;
            line-height: 1.6;
            max-width: 300px;
        }

        /* ── USER ROW ── */
        .user-row {
            display: flex;
            align-items: center;
            gap: 11px;
            background: var(--surface2);
            border: 1px solid var(--border2);
            border-radius: var(--r);
            padding: 10px 14px;
            margin-bottom: 22px;
        }
        .user-ava {
            width: 34px; height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--p), var(--p-dk));
            display: flex; align-items: center; justify-content: center;
            font-family: var(--fh);
            font-weight: 700; font-size: 0.8rem;
            color: white; flex-shrink: 0;
        }
        .user-info { flex: 1; min-width: 0; }
        .user-name {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-warm);
        }
        .user-meta {
            font-size: 0.67rem;
            color: var(--p);
            text-transform: uppercase;
            letter-spacing: 0.09em;
            font-weight: 600;
            margin-top: 1px;
        }
        .user-lock {
            color: var(--text-dim);
            font-size: 0.78rem;
        }

        /* ── ALERT ── */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            background: var(--red-dim);
            border: 1px solid rgba(255,77,109,0.18);
            border-radius: var(--r);
            padding: 11px 13px;
            margin-bottom: 20px;
            font-size: 0.82rem;
            color: #ff7090;
            line-height: 1.55;
        }
        .alert i { margin-top: 1px; flex-shrink: 0; }

        /* ── FIELD LABEL ── */
        .field-label {
            display: block;
            font-size: 0.67rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--text-muted);
            margin-bottom: 10px;
        }

        /* ── PROGRESS DOTS ── */
        .dots-row {
            display: flex;
            justify-content: center;
            gap: 9px;
            margin-bottom: 11px;
        }
        .dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--surface3);
            border: 1px solid var(--border2);
            transition: background 0.15s, border-color 0.15s, transform 0.15s;
        }
        .dot.on  { background: var(--p);    border-color: var(--p);    transform: scale(1.2); }
        .dot.done{ background: var(--teal); border-color: var(--teal); transform: scale(1.2); }

        /* ── OTP INPUT ── */
        .otp-wrap { position: relative; margin-bottom: 18px; }
        .otp-input {
            width: 100%;
            padding: 14px 40px 14px 14px;
            background: var(--surface2);
            border: 1px solid rgba(160,100,255,0.18);
            border-radius: var(--r);
            color: var(--text);
            font-family: var(--fh);
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: 0.5em;
            text-align: center;
            outline: none;
            transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
            caret-color: var(--p);
        }
        .otp-input::placeholder {
            color: var(--text-dim);
            letter-spacing: 0.25em;
            font-size: 1.35rem;
        }
        .otp-input:focus {
            border-color: var(--p);
            background: var(--surface3);
            box-shadow: 0 0 0 3px rgba(160,100,255,0.11);
        }
        .otp-clear-btn {
            position: absolute;
            right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--text-dim);
            cursor: pointer; font-size: 0.82rem;
            padding: 4px; display: none;
            transition: color 0.18s;
        }
        .otp-clear-btn:hover { color: var(--text-muted); }
        .otp-clear-btn.show { display: block; }

        /* ── SUBMIT BTN ── */
        .btn-verify {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--p), var(--p-dk));
            border: none;
            border-radius: var(--r);
            color: white;
            font-family: var(--fb);
            font-size: 0.875rem;
            font-weight: 600;
            letter-spacing: 0.01em;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            transition: opacity 0.18s, transform 0.18s;
            margin-bottom: 16px;
        }
        .btn-verify:hover { opacity: 0.88; transform: translateY(-1px); }
        .btn-verify:active { transform: translateY(0); opacity: 1; }

        /* ── DIVIDER ── */
        .or-divider {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
        }
        .or-divider::before, .or-divider::after {
            content: ''; flex: 1;
            height: 1px;
            background: var(--border);
        }
        .or-divider span {
            font-size: 0.68rem;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        /* ── BACKUP TOGGLE BTN ── */
        .btn-backup {
            width: 100%;
            padding: 10px;
            background: transparent;
            border: 1px solid var(--border2);
            border-radius: var(--r);
            color: var(--text-muted);
            font-family: var(--fb);
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 7px;
            transition: all 0.18s;
        }
        .btn-backup:hover {
            border-color: var(--p-border);
            color: var(--p-lt);
            background: var(--p-dim);
        }

        /* ── BACKUP PANEL ── */
        .backup-panel {
            display: none;
            margin-top: 14px;
            padding: 16px;
            background: var(--surface2);
            border: 1px solid var(--border2);
            border-radius: var(--r);
            animation: fadeUp 0.2s ease both;
        }
        .backup-panel.open { display: block; }

        .backup-hint {
            font-size: 0.75rem;
            color: var(--text-dim);
            margin-bottom: 10px;
            line-height: 1.55;
        }
        .backup-input {
            width: 100%;
            padding: 10px 12px;
            background: var(--surface3);
            border: 1px solid var(--border2);
            border-radius: var(--r);
            color: var(--text-warm);
            font-family: var(--fb);
            font-size: 0.875rem;
            outline: none;
            margin-bottom: 11px;
            transition: border-color 0.18s;
            caret-color: var(--p);
        }
        .backup-input::placeholder { color: var(--text-dim); }
        .backup-input:focus { border-color: var(--p); }

        .btn-backup-verify {
            width: 100%;
            padding: 10px;
            background: var(--p-dim);
            border: 1px solid var(--p-border);
            border-radius: var(--r);
            color: var(--p-lt);
            font-family: var(--fb);
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 7px;
            transition: all 0.18s;
        }
        .btn-backup-verify:hover { background: rgba(160,100,255,0.2); color: white; }

        /* ── CARD FOOTER ── */
        .card-footer {
            padding: 14px 32px 18px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .footer-muted { font-size: 0.78rem; color: var(--text-dim); }
        .footer-link {
            font-size: 0.78rem;
            color: var(--p);
            text-decoration: none;
            font-weight: 500;
            display: flex; align-items: center; gap: 5px;
            transition: opacity 0.18s;
        }
        .footer-link:hover { opacity: 0.75; }
    </style>
</head>
<body>

<!-- Wordmark -->
<div class="wordmark">
    <div class="wm-icon"><i class="fas fa-shield-halved"></i></div>
    <div class="wm-label">Trusted Midman <span>Secure Authentication</span></div>
</div>

<!-- Card -->
<div class="card">
    <div class="card-bar"></div>

    <div class="card-body">

        <!-- Icon + Title -->
        <div class="icon-header">
            <div class="icon-ring"><i class="fas fa-mobile-screen-button"></i></div>
            <div>
                <div class="icon-title">Two-step verification</div>
                <div class="icon-sub">Enter the 6-digit code from your authenticator app.</div>
            </div>
        </div>

        <!-- User -->
        <div class="user-row">
            <div class="user-ava"><?php echo strtoupper(substr($username,0,2)); ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                <div class="user-meta">Midman · 2FA Required</div>
            </div>
            <i class="fas fa-lock user-lock"></i>
        </div>

        <!-- Error -->
        <?php if($error): ?>
        <div class="alert">
            <i class="fas fa-circle-exclamation"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- OTP -->
        <label class="field-label">Verification code</label>

        <div class="dots-row">
            <?php for($i = 0; $i < 6; $i++): ?>
            <div class="dot" id="d<?php echo $i; ?>"></div>
            <?php endfor; ?>
        </div>

        <form method="POST" id="otpForm">
            <div class="otp-wrap">
                <input type="text" name="code" id="otpInput" class="otp-input"
                       maxlength="6" inputmode="numeric" autocomplete="one-time-code"
                       placeholder="• • • • • •" required>
                <button type="button" class="otp-clear-btn" id="clearBtn" onclick="clearCode()">
                    <i class="fas fa-xmark"></i>
                </button>
            </div>
            <button type="submit" class="btn-verify">
                <i class="fas fa-arrow-right-to-bracket"></i> Verify &amp; continue
            </button>
        </form>

        <!-- Divider -->
        <div class="or-divider"><span>or</span></div>

        <!-- Backup toggle -->
        <button class="btn-backup" id="backupBtn" onclick="toggleBackup()">
            <i class="fas fa-key" style="font-size:0.75rem;"></i>
            Use a backup code
        </button>

        <!-- Backup panel -->
        <div class="backup-panel" id="backupPanel">
            <div class="backup-hint">Each backup code can only be used once.</div>
            <form method="POST">
                <input type="hidden" name="use_backup" value="1">
                <label class="field-label">Backup code</label>
                <input type="text" name="code" class="backup-input"
                       placeholder="e.g. xxxxxxxx" autocomplete="off">
                <button type="submit" class="btn-backup-verify">
                    <i class="fas fa-key" style="font-size:0.75rem;"></i>
                    Verify backup code
                </button>
            </form>
        </div>

    </div><!-- /card-body -->

    <div class="card-footer">
        <span class="footer-muted">Wrong account?</span>
        <a href="login.php" class="footer-link">
            <i class="fas fa-arrow-left" style="font-size:0.6rem;"></i> Back to login
        </a>
    </div>
</div><!-- /card -->

<script>
    const otp   = document.getElementById('otpInput');
    const clear = document.getElementById('clearBtn');

    otp.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
        const n = this.value.length;

        for(let i = 0; i < 6; i++) {
            const d = document.getElementById('d' + i);
            d.className = i < n ? (n === 6 ? 'dot done' : 'dot on') : 'dot';
        }
        clear.className = n > 0 ? 'otp-clear-btn show' : 'otp-clear-btn';

        if(n === 6) setTimeout(() => document.getElementById('otpForm').submit(), 140);
    });

    function clearCode() {
        otp.value = '';
        otp.dispatchEvent(new Event('input'));
        otp.focus();
    }

    function toggleBackup() {
        const panel = document.getElementById('backupPanel');
        const btn   = document.getElementById('backupBtn');
        const open  = panel.classList.toggle('open');
        btn.innerHTML = open
            ? '<i class="fas fa-times" style="font-size:0.75rem;"></i> Cancel'
            : '<i class="fas fa-key" style="font-size:0.75rem;"></i> Use a backup code';
    }

    window.addEventListener('load', () => otp.focus());
</script>
</body>
</html>