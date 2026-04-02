<?php
require_once 'includes/config.php';

require_once 'includes/2fa-functions.php'; // at the top

$two_factor_enabled = false;
if($role == 'midman') {
    $two_factor_enabled = is2FAEnabled($user_id);
}

require_once 'includes/config.php';
require_once 'includes/2fa-functions.php';

if(!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];

$two_factor_enabled = false;
if($role == 'midman') {
    $two_factor_enabled = is2FAEnabled($user_id);
}

// System settings
$settings = mysqli_query($conn, "SELECT * FROM system_settings");
$sys = [];
while($r = mysqli_fetch_assoc($settings)) $sys[$r['setting_key']] = $r['setting_value'];
$min_payout = $sys['min_payout_amount'] ?? 50;

// Earnings
$earnings = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) as total_earned,
     COALESCE(SUM(CASE WHEN status='pending' THEN amount ELSE 0 END),0) as pending_earnings
     FROM earnings WHERE midman_id=$user_id"));
$total_earned = $earnings['total_earned'] ?? 0;

// Requested payouts
$requested = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(amount) as requested FROM payout_requests
     WHERE midman_id=$user_id AND status IN ('pending','approved')"))['requested'] ?? 0;

$available_balance = $total_earned - $requested;

$error = $success = '';
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_payout'])) {
    $amount          = floatval($_POST['amount']);
    $payment_method  = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $payment_details = mysqli_real_escape_string($conn, $_POST['payment_details']);
    if($amount <= 0)                    $error = 'Please enter a valid amount.';
    elseif($amount < $min_payout)       $error = "Minimum payout amount is \$$min_payout.";
    elseif($amount > $available_balance) $error = 'Insufficient balance.';
    else {
        $q = "INSERT INTO payout_requests (midman_id, amount, payment_method, payment_details, status)
              VALUES ($user_id, $amount, '$payment_method', '$payment_details', 'pending')";
        if(mysqli_query($conn, $q)) {
            $success = 'Payout request submitted! It will be processed within 24–48 hours.';
            $requested         += $amount;
            $available_balance -= $amount;
        } else {
            $error = 'Failed to submit request. Please try again.';
        }
    }
}

// Payout history
$payouts = mysqli_query($conn,
    "SELECT * FROM payout_requests WHERE midman_id=$user_id ORDER BY requested_at DESC LIMIT 10");

$display_name = $_SESSION['full_name'] ?? $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payouts — Trusted Midman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

        :root {
            --bg:         #0f0c08;
            --surface:    #0f0b07;
            --surface2:   #201a13;
            --surface3:   #271f16;
            --border:     rgba(255,180,80,0.08);
            --border2:    rgba(255,180,80,0.15);
            --border3:    rgba(255,180,80,0.24);
            /* midman purple */
            --purple:     #a064ff;
            --purple-lt:  #be8fff;
            --purple-dim: rgba(160,100,255,0.12);
            --purple-glow:rgba(160,100,255,0.28);
            /* semantic */
            --teal:       #00d4aa;
            --teal-dim:   rgba(0,212,170,0.11);
            --red:        #ff4d6d;
            --red-dim:    rgba(255,77,109,0.12);
            --orange:     #ff9632;
            --orange-dim: rgba(255,150,50,0.12);
            --blue:       #4e9fff;
            --blue-dim:   rgba(78,159,255,0.12);
            --gold:       #f0a500;
            --gold-dim:   rgba(240,165,0,0.13);
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

        html { scroll-behavior:smooth; }
        body { font-family:var(--font-body); background:var(--bg); color:var(--text-warm); min-height:100vh; overflow-x:hidden; -webkit-font-smoothing:antialiased; }
        .layout { display:flex; min-height:100vh; }

        @keyframes fadeUp { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);} }

        /* ── SIDEBAR ── */
        .sidebar { width:var(--sidebar-w); background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh; z-index:100; transition:transform 0.35s cubic-bezier(.77,0,.18,1); }
        .sidebar::before { content:''; position:absolute; bottom:-80px; left:-80px; width:300px; height:300px; background:radial-gradient(circle,rgba(120,60,200,0.09) 0%,transparent 65%); pointer-events:none; }
        .sidebar-logo { display:flex; align-items:center; gap:12px; padding:26px 22px; text-decoration:none; border-bottom:1px solid var(--border); position:relative; z-index:1; }
        .logo-icon { width:38px; height:38px; background:linear-gradient(135deg,var(--purple),#7040cc); border-radius:10px; display:flex; align-items:center; justify-content:center; color:white; font-size:16px; flex-shrink:0; box-shadow:0 0 20px var(--purple-glow); }
        .logo-text { font-family:var(--font-head); font-weight:700; font-size:1.1rem; color:var(--text); line-height:1.2; letter-spacing:-0.01em; }
        .logo-sub  { font-size:0.65rem; color:var(--purple); letter-spacing:0.12em; text-transform:uppercase; display:block; font-family:var(--font-body); font-weight:600; }
        .sidebar-nav { flex:1; padding:18px 10px; overflow-y:auto; position:relative; z-index:1; }
        .nav-label { font-size:0.65rem; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:var(--text-dim); padding:12px 12px 7px; }
        .nav-link { display:flex; align-items:center; gap:11px; padding:10px 13px; border-radius:var(--radius-sm); text-decoration:none; color:var(--text-muted); font-size:0.9rem; font-weight:500; margin-bottom:2px; transition:all 0.2s; position:relative; }
        .nav-link:hover { color:var(--text-warm); background:var(--surface2); }
        .nav-link.active { color:var(--purple); background:var(--purple-dim); border:1px solid rgba(160,100,255,0.14); }
        .nav-link.active::before { content:''; position:absolute; left:0; top:20%; bottom:20%; width:3px; background:var(--purple); border-radius:0 3px 3px 0; }
        .nav-icon { width:20px; text-align:center; font-size:0.9rem; flex-shrink:0; }
        .nav-badge { margin-left:auto; background:var(--red-dim); color:var(--red); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(255,77,109,0.15); }
        .sidebar-footer { padding:14px; border-top:1px solid var(--border); position:relative; z-index:1; }
        .user-pill { display:flex; align-items:center; gap:10px; padding:10px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); }
        .ava { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--purple),#7040cc); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.85rem; color:white; flex-shrink:0; box-shadow:0 0 10px var(--purple-glow); }
        .pill-name { font-size:0.875rem; font-weight:500; color:var(--text-warm); }
        .pill-role { font-size:0.68rem; color:var(--purple); text-transform:uppercase; letter-spacing:0.09em; }

        /* ── MAIN ── */
        .main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; }
        .topbar { position:sticky; top:0; z-index:50; background:rgba(15,12,8,0.88); backdrop-filter:blur(24px); border-bottom:1px solid var(--border); padding:0 32px; height:64px; display:flex; align-items:center; justify-content:space-between; }
        .topbar-left { display:flex; align-items:center; gap:16px; }
        .hamburger { display:none; background:none; border:none; color:var(--text-muted); font-size:1.1rem; cursor:pointer; padding:6px; border-radius:7px; transition:color 0.2s; }
        .hamburger:hover { color:var(--text-warm); }
        .page-title { font-family:var(--font-head); font-size:1.15rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .active-dot { display:flex; align-items:center; gap:7px; font-size:0.78rem; color:var(--text-muted); }
        .active-dot::before { content:''; width:7px; height:7px; border-radius:50%; background:var(--purple); box-shadow:0 0 8px var(--purple); }
        .content { padding:28px 32px; flex:1; max-width:900px; }

        /* ── ALERTS ── */
        .alert { display:flex; align-items:center; gap:10px; padding:13px 16px; border-radius:var(--radius-sm); font-size:0.875rem; margin-bottom:20px; }
        .alert-success { background:var(--teal-dim);  color:var(--teal);  border:1px solid rgba(0,212,170,0.22); }
        .alert-error   { background:var(--red-dim);   color:#ff7090;      border:1px solid rgba(255,77,109,0.22); }

        /* ── BALANCE HERO ── */
        .balance-hero {
            background:var(--surface); border:1px solid rgba(160,100,255,0.2);
            border-radius:var(--radius-lg); padding:30px 36px; margin-bottom:22px;
            position:relative; overflow:hidden;
            opacity:0; animation:fadeUp 0.45s ease forwards;
        }
        .balance-hero::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(160,100,255,0.4),transparent); }
        .bh-glow1 { position:absolute; top:-60px; right:-60px; width:260px; height:260px; background:radial-gradient(circle,rgba(160,100,255,0.2) 0%,transparent 65%); pointer-events:none; }
        .bh-glow2 { position:absolute; bottom:-40px; left:180px; width:180px; height:180px; background:radial-gradient(circle,rgba(0,212,170,0.06) 0%,transparent 65%); pointer-events:none; }
        .bh-inner { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:24px; position:relative; z-index:1; }

        .bh-left {}
        .bh-eyebrow { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.14em; color:var(--purple); margin-bottom:8px; }
        .bh-amount { font-family:var(--font-head); font-size:3.2rem; font-weight:800; color:var(--text); line-height:1; letter-spacing:-0.02em; margin-bottom:4px; }
        .bh-amount span { color:var(--purple); }
        .bh-hint { font-size:0.82rem; color:var(--text-muted); }

        .bh-stats { display:flex; gap:24px; flex-wrap:wrap; }
        .bh-stat { text-align:center; padding:14px 20px; background:rgba(160,100,255,0.08); border:1px solid rgba(160,100,255,0.14); border-radius:var(--radius-sm); min-width:110px; }
        .bh-stat-val { font-family:var(--font-head); font-size:1.3rem; font-weight:800; color:var(--text); letter-spacing:-0.01em; line-height:1; margin-bottom:4px; }
        .bh-stat-lbl { font-size:0.72rem; color:var(--text-muted); }

        /* ── INFO BOX ── */
        .info-box {
            background:rgba(160,100,255,0.07); border:1px solid rgba(160,100,255,0.18);
            border-radius:var(--radius-sm); padding:14px 18px; margin-bottom:20px;
            display:flex; align-items:center; gap:10px; font-size:0.875rem; color:var(--text-muted);
        }
        .info-box i { color:var(--purple); flex-shrink:0; }
        .info-box strong { color:var(--text-warm); }

        /* ── FORM PANEL ── */
        .form-panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden; margin-bottom:22px;
            opacity:0; animation:fadeUp 0.45s ease 0.1s forwards;
            position:relative;
        }
        .form-panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(160,100,255,0.2),transparent); }
        .form-head { display:flex; align-items:center; gap:10px; padding:16px 22px; border-bottom:1px solid var(--border); }
        .fh-icon  { width:28px; height:28px; border-radius:7px; background:var(--purple-dim); color:var(--purple); border:1px solid rgba(160,100,255,0.14); display:flex; align-items:center; justify-content:center; font-size:0.78rem; }
        .fh-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .form-body { padding:22px; }

        /* ── FORM ELEMENTS ── */
        .form-group { display:flex; flex-direction:column; gap:6px; margin-bottom:18px; }
        .form-group:last-of-type { margin-bottom:0; }
        .form-label { font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); }
        .form-hint  { font-size:0.72rem; color:var(--text-dim); }
        .form-control {
            width:100%; padding:11px 14px; background:var(--surface2);
            border:1px solid var(--border); border-radius:var(--radius-sm);
            color:var(--text-warm); font-family:var(--font-body); font-size:0.9rem;
            transition:all 0.22s; outline:none;
        }
        .form-control:focus { border-color:var(--purple); box-shadow:0 0 0 3px rgba(160,100,255,0.12); background:var(--surface3); }
        .form-control::placeholder { color:var(--text-dim); }
        select.form-control option { background:#201a13; }
        textarea.form-control { resize:vertical; min-height:100px; line-height:1.6; }

        /* payment method cards */
        .method-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; }
        .method-opt { cursor:pointer; }
        .method-opt input[type="radio"] { display:none; }
        .method-box {
            display:flex; flex-direction:column; align-items:center; gap:8px;
            padding:14px 10px; background:var(--surface2); border:2px solid var(--border);
            border-radius:var(--radius-sm); transition:all 0.22s; text-align:center;
        }
        .method-opt input:checked + .method-box { border-color:var(--purple); background:var(--purple-dim); }
        .method-box:hover { border-color:var(--border2); background:var(--surface3); }
        .method-icon { width:36px; height:36px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:0.9rem; background:var(--surface); border:1px solid var(--border); transition:all 0.22s; }
        .method-opt input:checked + .method-box .method-icon { background:var(--purple); color:white; border-color:var(--purple); }
        .method-name { font-size:0.78rem; font-weight:600; color:var(--text-warm); }

        /* ── SUBMIT BUTTON ── */
        .btn-submit {
            width:100%; padding:13px; margin-top:20px;
            background:linear-gradient(135deg,var(--purple),#7040cc);
            color:white; font-family:var(--font-head); font-size:1rem; font-weight:800;
            letter-spacing:-0.01em; border:none; border-radius:var(--radius-sm);
            cursor:pointer; display:flex; align-items:center; justify-content:center; gap:10px;
            box-shadow:0 6px 24px var(--purple-glow), 0 1px 0 rgba(255,255,255,0.08) inset;
            transition:all 0.25s;
        }
        .btn-submit:hover { background:linear-gradient(135deg,var(--purple-lt),var(--purple)); transform:translateY(-2px); box-shadow:0 10px 32px rgba(160,100,255,0.4); }

        /* ── HISTORY PANEL ── */
        .history-panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden;
            opacity:0; animation:fadeUp 0.45s ease 0.18s forwards;
            position:relative;
        }
        .history-panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(160,100,255,0.15),transparent); }
        .hp-head { display:flex; align-items:center; justify-content:space-between; padding:15px 20px; border-bottom:1px solid var(--border); }
        .hp-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:8px; letter-spacing:-0.01em; }
        .pti { width:26px; height:26px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.72rem; }
        .pti-purple { background:var(--purple-dim); color:var(--purple); border:1px solid rgba(160,100,255,0.14); }

        /* ── TABLE ── */
        .hist-table { width:100%; border-collapse:collapse; }
        .hist-table thead tr { border-bottom:1px solid var(--border); }
        .hist-table th { padding:11px 18px; font-size:0.67rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); text-align:left; background:var(--surface2); }
        .hist-table td { padding:14px 18px; border-bottom:1px solid var(--border); font-size:0.875rem; vertical-align:middle; }
        .hist-table tr:last-child td { border-bottom:none; }
        .hist-table tbody tr { transition:background 0.18s; }
        .hist-table tbody tr:hover td { background:rgba(160,100,255,0.04); }
        .amount-val { font-family:var(--font-head); font-weight:800; font-size:0.95rem; color:var(--purple); letter-spacing:-0.01em; }
        .method-badge { font-size:0.75rem; font-weight:600; color:var(--text-muted); display:flex; align-items:center; gap:5px; }
        .p-date { font-size:0.78rem; color:var(--text-dim); }

        .sbadge { font-size:0.62rem; font-weight:700; text-transform:uppercase; padding:3px 9px; border-radius:20px; letter-spacing:0.05em; border:1px solid transparent; }
        .status-pending   { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.15); }
        .status-approved  { background:var(--blue-dim);   color:var(--blue);   border-color:rgba(78,159,255,0.15); }
        .status-completed { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.15); }
        .status-rejected  { background:var(--red-dim);    color:var(--red);    border-color:rgba(255,77,109,0.15); }

        /* ── EMPTY ── */
        .empty { text-align:center; padding:50px 24px; }
        .empty-icon { width:64px; height:64px; background:var(--surface2); border:1px solid var(--border); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.6rem; color:var(--text-dim); margin:0 auto 14px; }
        .empty h4 { font-family:var(--font-head); color:var(--text-warm); margin-bottom:6px; letter-spacing:-0.01em; }
        .empty p  { font-size:0.84rem; color:var(--text-muted); }

        /* insufficient balance notice */
        .insuf-notice {
            background:rgba(160,100,255,0.06); border:1px solid rgba(160,100,255,0.18);
            border-radius:var(--radius-sm); padding:20px; margin-bottom:22px;
            text-align:center;
        }
        .insuf-notice i { font-size:1.8rem; color:var(--purple); opacity:0.5; display:block; margin-bottom:10px; }
        .insuf-notice p { font-size:0.875rem; color:var(--text-muted); }
        .insuf-notice strong { color:var(--text-warm); }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:820px) { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} .bh-inner{flex-direction:column;} .bh-stats{width:100%;justify-content:space-between;} }
        @media(max-width:540px) { .bh-stats{gap:10px;} .bh-stat{min-width:80px;padding:12px;} .method-grid{grid-template-columns:1fr 1fr;} }
    </style>
</head>
<body>
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <a href="index.php" class="sidebar-logo">
            <div class="logo-icon"><i class="fas fa-shield-halved"></i></div>
            <div class="logo-text">Trusted Midman <span class="logo-sub">Marketplace</span></div>
        </a>
        <nav class="sidebar-nav">
            <div class="nav-label">Midman</div>
            <a href="midman-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
            <a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-handshake"></i></span> Transactions</a>
            <a href="midman-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
            <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <a href="verify-identity.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span> KYC Status</a>
            <a href="payouts.php"          class="nav-link active"><span class="nav-icon"><i class="fas fa-wallet"></i></span> Payouts</a>

             <div class="nav-label" style="margin-top:10px;">Security</div>
            <a href="setup-2fa.php" class="nav-link">
                <span class="nav-icon"><i class="fas fa-shield-alt"></i></span>
                <?php echo $two_factor_enabled ? 'Manage 2FA' : 'Enable 2FA'; ?>
                <?php if($two_factor_enabled): ?><span class="security-badge">Active</span><?php endif; ?>
            </a>

            <div class="nav-label" style="margin-top:10px;">Account</div>
            <a href="profile.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a>
            <a href="logout.php"  class="nav-link" style="color:var(--text-dim);margin-top:6px;"><span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="ava"><?php echo strtoupper(substr($_SESSION['username'],0,2)); ?></div>
                <div>
                    <div class="pill-name"><?php echo htmlspecialchars($display_name); ?></div>
                    <div class="pill-role">Midman</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <span class="page-title">Payouts</span>
            </div>
            <div class="active-dot">Active</div>
        </header>

        <div class="content">

            <?php if($success): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- BALANCE HERO -->
            <div class="balance-hero">
                <div class="bh-glow1"></div>
                <div class="bh-glow2"></div>
                <div class="bh-inner">
                    <div class="bh-left">
                        <div class="bh-eyebrow">Available Balance</div>
                        <div class="bh-amount">$<span><?php echo number_format($available_balance,2); ?></span></div>
                        <div class="bh-hint">Minimum payout: $<?php echo $min_payout; ?> &nbsp;·&nbsp; Processing: 24–48 hrs</div>
                    </div>
                    <div class="bh-stats">
                        <div class="bh-stat">
                            <div class="bh-stat-val">$<?php echo number_format($total_earned,2); ?></div>
                            <div class="bh-stat-lbl">Total Earned</div>
                        </div>
                        <div class="bh-stat">
                            <div class="bh-stat-val">$<?php echo number_format($requested,2); ?></div>
                            <div class="bh-stat-lbl">Pending Payouts</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- INFO BOX -->
            <div class="info-box">
                <i class="fas fa-circle-info"></i>
                <div><strong>How payouts work:</strong> Submit a request with your preferred payment method. Approved requests are usually processed within 24–48 hours. Minimum payout is <strong>$<?php echo $min_payout; ?></strong>.</div>
            </div>

            <!-- REQUEST FORM -->
            <?php if($available_balance >= $min_payout): ?>
            <div class="form-panel">
                <div class="form-head">
                    <div class="fh-icon"><i class="fas fa-paper-plane"></i></div>
                    <span class="fh-title">Request Payout</span>
                </div>
                <div class="form-body">
                    <form method="POST" action="">

                        <div class="form-group">
                            <label class="form-label">Amount (USD) <span style="color:var(--red);text-transform:none;font-size:0.8rem;">*</span></label>
                            <input type="number" name="amount" class="form-control"
                                   min="<?php echo $min_payout; ?>"
                                   max="<?php echo $available_balance; ?>"
                                   step="0.01" required
                                   placeholder="Enter amount to withdraw">
                            <span class="form-hint">Min: $<?php echo $min_payout; ?> &nbsp;·&nbsp; Max: $<?php echo number_format($available_balance,2); ?></span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Method <span style="color:var(--red);text-transform:none;font-size:0.8rem;">*</span></label>
                            <div class="method-grid">
                                <label class="method-opt">
                                    <input type="radio" name="payment_method" value="paypal" required>
                                    <div class="method-box">
                                        <div class="method-icon" style="color:var(--blue);"><i class="fab fa-paypal"></i></div>
                                        <div class="method-name">PayPal</div>
                                    </div>
                                </label>
                                <label class="method-opt">
                                    <input type="radio" name="payment_method" value="gcash">
                                    <div class="method-box">
                                        <div class="method-icon" style="color:var(--teal);"><i class="fas fa-mobile-screen"></i></div>
                                        <div class="method-name">GCash</div>
                                    </div>
                                </label>
                                <label class="method-opt">
                                    <input type="radio" name="payment_method" value="bank_transfer">
                                    <div class="method-box">
                                        <div class="method-icon" style="color:var(--orange);"><i class="fas fa-building-columns"></i></div>
                                        <div class="method-name">Bank Transfer</div>
                                    </div>
                                </label>
                                <label class="method-opt">
                                    <input type="radio" name="payment_method" value="other">
                                    <div class="method-box">
                                        <div class="method-icon" style="color:var(--purple);"><i class="fas fa-circle-nodes"></i></div>
                                        <div class="method-name">Other</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Details <span style="color:var(--red);text-transform:none;font-size:0.8rem;">*</span></label>
                            <textarea name="payment_details" class="form-control" required
                                placeholder="Enter your PayPal email, GCash number, or bank account details…"></textarea>
                            <span class="form-hint">Double-check your details — incorrect information may delay your payout.</span>
                        </div>

                        <button type="submit" name="request_payout" class="btn-submit">
                            <i class="fas fa-paper-plane"></i> Submit Payout Request
                        </button>

                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="insuf-notice">
                <i class="fas fa-lock"></i>
                <p>Your available balance of <strong>$<?php echo number_format($available_balance,2); ?></strong> is below the minimum payout threshold of <strong>$<?php echo $min_payout; ?></strong>.<br>Complete more transactions to unlock payouts.</p>
            </div>
            <?php endif; ?>

            <!-- PAYOUT HISTORY -->
            <div class="history-panel">
                <div class="hp-head">
                    <div class="hp-title">
                        <div class="pti pti-purple"><i class="fas fa-clock-rotate-left"></i></div>
                        Payout History
                    </div>
                </div>

                <?php if(mysqli_num_rows($payouts) > 0): ?>
                <table class="hist-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $method_icons = [
                        'paypal'        => ['fa-paypal fab', 'var(--blue)'],
                        'gcash'         => ['fa-mobile-screen fas', 'var(--teal)'],
                        'bank_transfer' => ['fa-building-columns fas', 'var(--orange)'],
                        'other'         => ['fa-circle-nodes fas', 'var(--purple)'],
                    ];
                    while($p = mysqli_fetch_assoc($payouts)):
                        $mi = $method_icons[$p['payment_method']] ?? ['fa-wallet fas','var(--text-muted)'];
                    ?>
                    <tr>
                        <td><span class="p-date"><?php echo date('M d, Y', strtotime($p['requested_at'])); ?></span></td>
                        <td><div class="amount-val">$<?php echo number_format($p['amount'],2); ?></div></td>
                        <td>
                            <span class="method-badge">
                                <i class="<?php echo $mi[0]; ?>" style="color:<?php echo $mi[1]; ?>;font-size:0.8rem;"></i>
                                <?php echo ucfirst(str_replace('_',' ',$p['payment_method'])); ?>
                            </span>
                        </td>
                        <td>
                            <span class="sbadge status-<?php echo $p['status']; ?>">
                                <?php echo ucfirst($p['status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty">
                    <div class="empty-icon"><i class="fas fa-receipt"></i></div>
                    <h4>No payout requests yet</h4>
                    <p>Your payout history will appear here once you submit your first request.</p>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

<script>
    const ham = document.getElementById('hamburger');
    const sb  = document.getElementById('sidebar');
    const ov  = document.getElementById('overlay');
    ham.addEventListener('click', () => { sb.classList.toggle('open'); ov.classList.toggle('visible'); });
    ov.addEventListener('click',  () => { sb.classList.remove('open'); ov.classList.remove('visible'); });

    // highlight selected payment method card
    document.querySelectorAll('.method-opt input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.querySelectorAll('.method-box').forEach(b => b.style.removeProperty('border-color'));
        });
    });
</script>
</body>
</html>