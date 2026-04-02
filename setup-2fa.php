<?php
require_once 'includes/config.php';
require_once 'includes/2fa-functions.php';


$pending_tx_count = 0;
if($_SESSION['role'] === 'seller') {
    $ptq = mysqli_query($conn, "SELECT COUNT(*) c FROM transactions WHERE seller_id={$_SESSION['user_id']} AND status='pending'");
    $pending_tx_count = mysqli_fetch_assoc($ptq)['c'];
} elseif($_SESSION['role'] === 'midman') {
    $ptq = mysqli_query($conn, "SELECT COUNT(*) c FROM transactions WHERE midman_id={$_SESSION['user_id']} AND status='pending'");
    $pending_tx_count = mysqli_fetch_assoc($ptq)['c'];
}

if(!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$role     = $_SESSION['role'] ?? 'buyer';
$error    = '';
$success  = '';

$two_factor_enabled = is2FAEnabled($user_id);
$secret = get2FASecret($user_id);
if(!$secret && !$two_factor_enabled) $secret = generate2FASecret($user_id);
$qrCodeUrl = generate2FAQRCode($user_id, $username, $secret);

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enable_2fa'])) {
    if(verify2FACode($user_id, $_POST['code'])) {
        mysqli_query($conn, "UPDATE users SET two_factor_enabled=1 WHERE id=$user_id");
        $backup_codes = generateBackupCodes($user_id);
        $_SESSION['backup_codes'] = $backup_codes;
        $success = 'Two-factor authentication enabled successfully!';
        $two_factor_enabled = true;
    } else {
        $error = 'Invalid verification code. Please try again.';
    }
}

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['disable_2fa'])) {
    if(verify2FACode($user_id, $_POST['code'])) {
        mysqli_query($conn, "UPDATE users SET two_factor_enabled=0, two_factor_secret=NULL, two_factor_backup_codes=NULL WHERE id=$user_id");
        $success = 'Two-factor authentication has been disabled.';
        $two_factor_enabled = false;
        $secret = generate2FASecret($user_id);
        $qrCodeUrl = generate2FAQRCode($user_id, $username, $secret);
    } else {
        $error = 'Invalid verification code. Please try again.';
    }
}

$display_name = $_SESSION['full_name'] ?? $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication — Trusted Midman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

        :root {
            --bg:         #0f0c08;
            --bg2:        #130f0a;
            --surface:    #0f0b07;
            --surface2:   #201a13;
            --surface3:   #271f16;
            --border:     rgba(255,180,80,0.08);
            --border2:    rgba(255,180,80,0.15);
            --border3:    rgba(255,180,80,0.24);
            
            /* Accent colors – default gold for buyers/sellers */
            --accent:       #f0a500;
            --accent-lt:    #ffbe3a;
            --accent-dim:   rgba(240,165,0,0.13);
            --accent-glow:  rgba(240,165,0,0.28);
            --gradient-start: #f0a500;
            --gradient-end:   #d4920a;
            
            /* Semantic colors */
            --teal:       #00d4aa;
            --teal-dim:   rgba(0,212,170,0.11);
            --red:        #ff4d6d;
            --red-dim:    rgba(255,77,109,0.12);
            --orange:     #ff9632;
            --orange-dim: rgba(255,150,50,0.12);
            --blue:       #4e9fff;
            --blue-dim:   rgba(78,159,255,0.12);
            --purple:     #a064ff;
            --purple-dim: rgba(160,100,255,0.12);
            --purple-glow:rgba(160,100,255,0.28);
            
            --text:       #ffffff;
            --text-warm:  #f0e8da;
            --text-muted: #a89880;
            --text-dim:   #5a4e3a;
            --radius-sm:  10px;
            --radius:     14px;
            --radius-lg:  20px;
            --sidebar-w:  260px;
            --font-head:  'Barlow Condensed', sans-serif;
            --font-body:  'DM Sans', sans-serif;
        }

        /* Midman override – switch gold to purple */
        body.role-midman {
            --accent:       var(--purple);
            --accent-lt:    #be8fff;
            --accent-dim:   rgba(160,100,255,0.12);
            --accent-glow:  rgba(160,100,255,0.28);
            --gradient-start: #a064ff;
            --gradient-end:   #7040cc;
        }

        html { scroll-behavior:smooth; }
        body { font-family:var(--font-body); background:var(--bg); color:var(--text-warm); min-height:100vh; overflow-x:hidden; -webkit-font-smoothing:antialiased; }
        .layout { display:flex; min-height:100vh; }

        @keyframes fadeUp { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);} }

        /* ── SIDEBAR ── */
        .sidebar { width:var(--sidebar-w); background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh; z-index:100; transition:transform 0.35s cubic-bezier(.77,0,.18,1); }
        .sidebar::before { content:''; position:absolute; bottom:-80px; left:-80px; width:300px; height:300px; background:radial-gradient(circle,rgba(200,100,0,0.08) 0%,transparent 65%); pointer-events:none; }
        .sidebar-logo { display:flex; align-items:center; gap:12px; padding:26px 22px; text-decoration:none; border-bottom:1px solid var(--border); position:relative; z-index:1; }
   .logo-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.logo-icon img {
    width: 100%;
    height: 100%;
    object-fit: contain;
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

        .logo-text { font-family:var(--font-head); font-weight:700; font-size:1.1rem; color:var(--text); line-height:1.2; letter-spacing:-0.01em; }
        .logo-sub  { font-size:0.65rem; color:var(--accent); letter-spacing:0.12em; text-transform:uppercase; display:block; font-family:var(--font-body); font-weight:600; }
        .sidebar-nav { flex:1; padding:18px 10px; overflow-y:auto; position:relative; z-index:1; }
        .nav-label { font-size:0.65rem; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:var(--text-dim); padding:12px 12px 7px; }
        .nav-link { display:flex; align-items:center; gap:11px; padding:10px 13px; border-radius:var(--radius-sm); text-decoration:none; color:var(--text-muted); font-size:0.9rem; font-weight:500; margin-bottom:2px; transition:all 0.2s; position:relative; }
        .nav-link:hover { color:var(--text-warm); background:var(--surface2); }
        .nav-link.active { color:var(--accent); background:var(--accent-dim); border:1px solid rgba(240,165,0,0.12); }
        .nav-link.active::before { content:''; position:absolute; left:0; top:20%; bottom:20%; width:3px; background:var(--accent); border-radius:0 3px 3px 0; }
        .nav-icon { width:20px; text-align:center; font-size:0.9rem; flex-shrink:0; }
        .security-badge { margin-left:auto; background:var(--teal-dim); color:var(--teal); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(0,212,170,0.15); }
        .sidebar-footer { padding:14px; border-top:1px solid var(--border); position:relative; z-index:1; }
        .user-pill { display:flex; align-items:center; gap:10px; padding:10px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); }
        .ava { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.85rem; color:#0f0c08; flex-shrink:0; box-shadow:0 0 10px var(--accent-glow); }
        .pill-name { font-size:0.875rem; font-weight:500; color:var(--text-warm); }
        .pill-role { font-size:0.68rem; color:var(--accent); text-transform:uppercase; letter-spacing:0.09em; }

        /* ── MAIN ── */
        .main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; }
        .topbar { position:sticky; top:0; z-index:50; background:rgba(15,12,8,0.88); backdrop-filter:blur(24px); border-bottom:1px solid var(--border); padding:0 32px; height:64px; display:flex; align-items:center; justify-content:space-between; }
        .topbar-left { display:flex; align-items:center; gap:16px; }
        .hamburger { display:none; background:none; border:none; color:var(--text-muted); font-size:1.1rem; cursor:pointer; padding:6px; border-radius:7px; transition:color 0.2s; }
        .hamburger:hover { color:var(--text-warm); }
        .page-title { font-family:var(--font-head); font-size:1.15rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .content { padding:28px 32px; flex:1; display:flex; justify-content:center; }
        .inner { width:100%; max-width:680px; }

        /* ── ALERTS ── */
        .alert { display:flex; align-items:center; gap:10px; padding:13px 16px; border-radius:var(--radius-sm); font-size:0.85rem; margin-bottom:20px; }
        .alert-success { background:var(--teal-dim); border:1px solid rgba(0,212,170,0.22); color:var(--teal); }
        .alert-error   { background:var(--red-dim); border:1px solid rgba(255,77,109,0.22); color:var(--red); }

        /* ── STATUS HERO ── */
        .status-hero { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); padding:24px 28px; margin-bottom:22px; display:flex; align-items:center; gap:16px; position:relative; overflow:hidden; opacity:0; animation:fadeUp 0.4s ease forwards; }
        .status-hero::before { content:''; position:absolute; top:-40px; right:-40px; width:160px; height:160px; border-radius:50%; pointer-events:none; opacity:0.12; }
        .sh-enabled::before { background:var(--teal); }
        .sh-disabled::before { background:var(--red); }
        .sh-status-icon { width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; }
        .sh-enabled .sh-status-icon { background:var(--teal-dim); color:var(--teal); }
        .sh-disabled .sh-status-icon { background:var(--red-dim); color:var(--red); }
        .sh-label { font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:3px; }
        .sh-enabled .sh-label { color:var(--teal); }
        .sh-disabled .sh-label { color:var(--red); }
        .sh-title { font-family:var(--font-head); font-size:1.2rem; font-weight:800; color:var(--text); margin-bottom:3px; }
        .sh-sub { font-size:0.8rem; color:var(--text-muted); }

        /* ── PANEL ── */
        .panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; margin-bottom:18px; opacity:0; animation:fadeUp 0.4s ease 0.1s forwards; }
        .panel-head { display:flex; align-items:center; gap:10px; padding:15px 20px; border-bottom:1px solid var(--border); }
        .ph-icon { width:28px; height:28px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.78rem; background:var(--accent-dim); color:var(--accent); }
        .ph-title { font-family:var(--font-head); font-size:0.9rem; font-weight:700; color:var(--text); }
        .panel-body { padding:22px 20px; }

        /* ── STEPS ── */
        .step { display:flex; gap:14px; margin-bottom:24px; padding-bottom:24px; border-bottom:1px solid var(--border); }
        .step:last-of-type { border-bottom:none; margin-bottom:0; padding-bottom:0; }
        .step-num { width:34px; height:34px; border-radius:50%; background:var(--accent-dim); color:var(--accent); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:800; font-size:0.85rem; flex-shrink:0; box-shadow:0 0 0 4px var(--accent-dim); }
        .step-title { font-family:var(--font-head); font-size:0.9rem; font-weight:700; color:var(--text); margin-bottom:4px; }
        .step-desc { font-size:0.8rem; color:var(--text-muted); line-height:1.6; margin-bottom:10px; }

        /* ── APP BADGES ── */
        .app-badges { display:flex; gap:8px; flex-wrap:wrap; margin-top:8px; }
        .app-badge { display:inline-flex; align-items:center; gap:6px; padding:6px 12px; background:var(--surface2); border:1px solid var(--border2); border-radius:var(--radius-sm); font-size:0.75rem; color:var(--text-muted); text-decoration:none; transition:all 0.2s; }
        .app-badge:hover { border-color:var(--accent); color:var(--text); }
        .app-badge i { font-size:1rem; }
         .nav-badge { margin-left:auto; background:var(--red-dim); color:var(--red); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(255,77,109,0.15); }

        /* ── QR CODE ── */
        .qr-wrap { display:flex; gap:16px; align-items:flex-start; flex-wrap:wrap; margin:10px 0; }
        .qr-box { background:white; padding:14px; border-radius:var(--radius-sm); display:inline-block; flex-shrink:0; }
        .qr-box img { display:block; width:150px; height:150px; }
        .manual-key { flex:1; min-width:200px; }
        .mk-label { font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-dim); margin-bottom:6px; }
        .mk-code { background:var(--surface2); border:1px solid var(--border2); border-radius:var(--radius-sm); padding:11px 14px; font-family:'Courier New',monospace; font-size:0.85rem; color:var(--text); letter-spacing:0.08em; word-break:break-all; cursor:pointer; transition:border-color 0.2s; position:relative; }
        .mk-code:hover { border-color:var(--accent); }
        .mk-copy { font-size:0.7rem; color:var(--text-dim); margin-top:5px; display:flex; align-items:center; gap:4px; }
        .mk-note { font-size:0.75rem; color:var(--text-dim); margin-top:8px; line-height:1.5; }

        /* ── OTP INPUT ── */
        .otp-wrap { display:flex; flex-direction:column; gap:8px; margin-top:10px; }
        .otp-input { width:100%; padding:13px; background:var(--surface2); border:1px solid var(--border2); border-radius:var(--radius-sm); color:var(--text); font-family:'Courier New',monospace; font-size:1.5rem; text-align:center; letter-spacing:10px; transition:border-color 0.2s,box-shadow 0.2s; }
        .otp-input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-dim); }
        .otp-input::placeholder { letter-spacing:4px; font-size:1rem; color:var(--text-dim); }

        /* ── BUTTONS ── */
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:11px 20px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.875rem; font-weight:600; cursor:pointer; border:none; transition:all 0.22s ease; width:100%; margin-top:10px; }
        .btn-accent { background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); color:#0f0c08; font-weight:700; box-shadow:0 3px 14px var(--accent-glow); }
        .btn-accent:hover { background:linear-gradient(135deg,var(--accent-lt),var(--accent)); transform:translateY(-2px); }
        .btn-red { background:var(--red-dim); color:var(--red); border:1px solid rgba(255,77,109,0.22); }
        .btn-red:hover { background:var(--red); color:white; transform:translateY(-2px); }
        .btn-ghost { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }
        .btn-ghost:hover { color:var(--text); }

        /* ── BACKUP CODES ── */
        .backup-panel { background:var(--surface); border:1px solid var(--accent-dim); border-radius:var(--radius); overflow:hidden; margin-bottom:18px; opacity:0; animation:fadeUp 0.4s ease 0.05s forwards; }
        .bp-head { background:var(--accent-dim); padding:14px 20px; border-bottom:1px solid rgba(240,165,0,0.15); display:flex; align-items:center; gap:8px; }
        .bp-title { font-family:var(--font-head); font-size:0.9rem; font-weight:700; color:var(--accent); }
        .bp-body { padding:18px 20px; }
        .bp-warn { font-size:0.8rem; color:var(--text-muted); margin-bottom:14px; display:flex; align-items:flex-start; gap:8px; line-height:1.55; }
        .bp-warn i { color:var(--orange); margin-top:2px; flex-shrink:0; }
        .codes-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:8px; }
        .code-item { background:var(--surface2); border:1px solid var(--border2); border-radius:var(--radius-sm); padding:10px 14px; font-family:'Courier New',monospace; font-size:0.85rem; color:var(--text); text-align:center; letter-spacing:0.1em; }

        /* ── DISABLE PANEL ── */
        .disable-panel { background:var(--surface); border:1px solid rgba(255,77,109,0.18); border-radius:var(--radius); overflow:hidden; margin-bottom:18px; opacity:0; animation:fadeUp 0.4s ease 0.15s forwards; }
        .dp-head { background:var(--red-dim); padding:14px 20px; border-bottom:1px solid rgba(255,77,109,0.15); display:flex; align-items:center; gap:8px; }
        .dp-title { font-family:var(--font-head); font-size:0.9rem; font-weight:700; color:var(--red); }
        .dp-body { padding:18px 20px; }
        .dp-desc { font-size:0.82rem; color:var(--text-muted); margin-bottom:14px; line-height:1.55; }

        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }

        @media(max-width:820px) { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
        @media(max-width:560px) { .qr-wrap{flex-direction:column;} .codes-grid{grid-template-columns:1fr;} .app-badges{flex-direction:column;} }
    </style>
</head>
<body class="role-<?php echo $role; ?>">
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <aside class="sidebar" id="sidebar">
        <a href="index.php" class="sidebar-logo">
    <div class="logo-icon">
        <img src="images/logowhite.png" alt="Trusted Midman" style="width:100%; height:100%; object-fit:cover;">
    </div>
    <div class="logo-text">
        Trusted Midman
        <span class="logo-sub">Marketplace</span>
    </div>
</a>

        <nav class="sidebar-nav">
            <?php if($role === 'midman'): ?>
                <div class="nav-label">Midman</div>
                <a href="midman-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span>Dashboard</a>
                <a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-handshake"></i></span> Transactions
            <?php if($pending_tx_count > 0): ?><span class="nav-badge"><?php echo $pending_tx_count; ?></span><?php endif; ?></a>
                <a href="midman-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span>Earnings</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span>Dispute Center</a>
                <a href="verify-identity.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span>KYC Status</a>

                <!-- Security section for midman -->
                <div class="nav-label" style="margin-top:10px;">Security</div>
                <a href="setup-2fa.php" class="nav-link active">
                    <span class="nav-icon"><i class="fas fa-shield-alt"></i></span>
                    <?php echo $two_factor_enabled ? 'Manage 2FA' : 'Enable 2FA'; ?>
                    <?php if($two_factor_enabled): ?><span class="security-badge">Active</span><?php endif; ?>
                </a>

                <div class="nav-label" style="margin-top:10px;">Account</div>
                <a href="profile.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span>Profile</a>
            <?php elseif($role === 'seller'): ?>
                <div class="nav-label">Seller</div>
                <a href="seller-dashboard.php"   class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span>Dashboard</a>
                <a href="my-products.php"        class="nav-link"><span class="nav-icon"><i class="fas fa-box-open"></i></span>My Products</a>
                <a href="add-gaming-product.php" class="nav-link"><span class="nav-icon"><i class="fas fa-plus-circle"></i></span>Add Product</a>
                <a href="my-transactions.php" class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span>Transactions</a>
                <a href="my-sales.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-chart-line"></i></span> Sales 
            <?php if($pending_tx_count > 0): ?><span class="nav-badge"><?php echo $pending_tx_count; ?></span><?php endif; ?></a>
                <a href="seller-earnings.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span>Earnings</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span>Dispute Center</a>

                <div class="nav-label" style="margin-top:10px;">Account</div>
                <a href="apply-midman.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span>Apply as Midman</a>
                <a href="profile.php"            class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span>Profile</a>
                <a href="setup-2fa.php"          class="nav-link active"><span class="nav-icon"><i class="fas fa-shield-alt"></i></span>2FA Security</a>
            <?php else: ?>
                <div class="nav-label">Buyer</div>
                <a href="buyer-dashboard.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span>Dashboard</a>
                <a href="products.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-store"></i></span>Browse Products</a>
                <a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span>My Purchases</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span>Dispute Center</a>

                <div class="nav-label" style="margin-top:10px;">Account</div>
                <a href="vapply-midman.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span>Apply as Midman</a>
                <a href="profile.php"          class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span>Profile</a>
                <a href="setup-2fa.php"        class="nav-link active"><span class="nav-icon"><i class="fas fa-shield-alt"></i></span>2FA Security</a>
            <?php endif; ?>
            <a href="logout.php" class="nav-link" style="color:var(--text-dim);margin-top:8px;"><span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span>Sign Out</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="ava"><?php echo strtoupper(substr($_SESSION['username']??'GU',0,2)); ?></div>
                <div>
                    <div class="pill-name"><?php echo htmlspecialchars($display_name); ?></div>
                    <div class="pill-role"><?php echo ucfirst($role); ?></div>
                </div>
            </div>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <span class="page-title">Two-Factor Authentication</span>
            </div>
            <!-- <a href="profile.php" style="font-size:0.78rem;color:var(--text-muted);text-decoration:none;display:flex;align-items:center;gap:6px;">
                <i class="fas fa-arrow-left"></i> Profile
            </a> -->
        </header>

        <div class="content">
            <div class="inner">

                <?php if($error): ?>
                    <div class="alert alert-error"><i class="fas fa-triangle-exclamation"></i><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if($success): ?>
                    <div class="alert alert-success"><i class="fas fa-circle-check"></i><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <!-- STATUS HERO -->
                <div class="status-hero <?php echo $two_factor_enabled ? 'sh-enabled' : 'sh-disabled'; ?>">
                    <div class="sh-status-icon">
                        <i class="fas <?php echo $two_factor_enabled ? 'fa-shield-halved' : 'fa-shield'; ?>"></i>
                    </div>
                    <div>
                        <div class="sh-label"><?php echo $two_factor_enabled ? '2FA Enabled' : '2FA Disabled'; ?></div>
                        <div class="sh-title">Two-Factor Authentication</div>
                        <div class="sh-sub">
                            <?php if($two_factor_enabled): ?>
                                Your account is protected with an authenticator app.
                            <?php else: ?>
                                Add an extra layer of security to your account.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if(!$two_factor_enabled): ?>
                <!-- ── SETUP PANEL ── -->
                <div class="panel">
                    <div class="panel-head">
                        <div class="ph-icon"><i class="fas fa-lock"></i></div>
                        <span class="ph-title">Set Up Authenticator App</span>
                    </div>
                    <div class="panel-body">

                        <!-- Step 1 -->
                        <div class="step">
                            <div class="step-num">1</div>
                            <div>
                                <div class="step-title">Install an Authenticator App</div>
                                <div class="step-desc">Download one of these free apps on your phone. Google Authenticator or Authy are recommended.</div>
                                <div class="app-badges">
                                    <span class="app-badge"><i class="fab fa-google-play"></i>Google Authenticator</span>
                                    <span class="app-badge"><i class="fab fa-app-store-ios"></i>Authy</span>
                                    <span class="app-badge"><i class="fas fa-mobile-screen"></i>Microsoft Authenticator</span>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2 -->
                        <div class="step">
                            <div class="step-num">2</div>
                            <div>
                                <div class="step-title">Scan the QR Code</div>
                                <div class="step-desc">Open your authenticator app, tap "Add account", then scan this code. Or enter the secret key manually.</div>
                                <div class="qr-wrap">
                                    <div class="qr-box">
                                        <img src="<?php echo htmlspecialchars($qrCodeUrl); ?>" alt="2FA QR Code">
                                    </div>
                                    <div class="manual-key">
                                        <div class="mk-label">Manual entry key</div>
                                        <div class="mk-code" id="secretKey" onclick="copySecret()"><?php echo htmlspecialchars($secret); ?></div>
                                        <div class="mk-copy" id="copyHint"><i class="fas fa-copy"></i> Click to copy</div>
                                        <div class="mk-note">Can't scan? Open your app → Add account → Enter setup key → paste the code above.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3 -->
                        <div class="step">
                            <div class="step-num">3</div>
                            <div>
                                <div class="step-title">Enter the 6-Digit Code</div>
                                <div class="step-desc">Type the rotating code shown in your authenticator app to confirm setup.</div>
                                <form method="POST">
                                    <div class="otp-wrap">
                                        <input type="text" name="code" class="otp-input" maxlength="6" pattern="[0-9]{6}" placeholder="000000" inputmode="numeric" autocomplete="one-time-code" required>
                                        <button type="submit" name="enable_2fa" class="btn btn-accent">
                                            <i class="fas fa-shield-halved"></i> Enable Two-Factor Auth
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>

                <?php else: ?>
                <!-- ── BACKUP CODES ── -->
                <?php if(isset($_SESSION['backup_codes'])): ?>
                <div class="backup-panel">
                    <div class="bp-head">
                        <i class="fas fa-key" style="color:var(--accent);"></i>
                        <span class="bp-title">Save Your Backup Codes</span>
                    </div>
                    <div class="bp-body">
                        <div class="bp-warn">
                            <i class="fas fa-triangle-exclamation"></i>
                            <span>These codes can each be used <strong>once</strong> if you lose access to your authenticator. Store them somewhere safe — they won't be shown again.</span>
                        </div>
                        <div class="codes-grid">
                            <?php foreach($_SESSION['backup_codes'] as $code): ?>
                                <div class="code-item"><?php echo htmlspecialchars($code); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <?php unset($_SESSION['backup_codes']); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ── DISABLE PANEL ── -->
                <div class="disable-panel">
                    <div class="dp-head">
                        <i class="fas fa-shield-halved" style="color:var(--red);font-size:0.85rem;"></i>
                        <span class="dp-title">Disable Two-Factor Authentication</span>
                    </div>
                    <div class="dp-body">
                        <div class="dp-desc">Disabling 2FA will make your account less secure. Enter your current authenticator code to confirm.</div>
                        <form method="POST">
                            <div class="otp-wrap">
                                <input type="text" name="code" class="otp-input" maxlength="6" pattern="[0-9]{6}" placeholder="000000" inputmode="numeric" autocomplete="one-time-code" required>
                                <button type="submit" name="disable_2fa" class="btn btn-red" onclick="return confirm('Are you sure you want to disable 2FA? This will reduce your account security.')">
                                    <i class="fas fa-shield-xmark"></i> Disable 2FA
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <a href="profile.php" class="btn btn-ghost" style="margin-top:4px;"><i class="fas fa-arrow-left"></i> Back to Profile</a>

            </div>
        </div>
    </main>
</div>

<script>
    const ham=document.getElementById('hamburger'),sb=document.getElementById('sidebar'),ov=document.getElementById('overlay');
    ham.addEventListener('click',()=>{sb.classList.toggle('open');ov.classList.toggle('visible');});
    ov.addEventListener('click',()=>{sb.classList.remove('open');ov.classList.remove('visible');});

    function copySecret() {
        const key = document.getElementById('secretKey').textContent.trim();
        navigator.clipboard.writeText(key).then(() => {
            const hint = document.getElementById('copyHint');
            hint.innerHTML = '<i class="fas fa-circle-check"></i> Copied!';
            hint.style.color = 'var(--teal)';
            setTimeout(() => {
                hint.innerHTML = '<i class="fas fa-copy"></i> Click to copy';
                hint.style.color = '';
            }, 2000);
        });
    }
</script>
</body>
</html>