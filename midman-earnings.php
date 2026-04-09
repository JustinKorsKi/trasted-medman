<?php
require_once 'includes/config.php';
require_once 'includes/2fa-functions.php';

// Check if user is logged in and is a midman
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'midman') {
    header('Location: login.php');
    exit();
}

$pending_tx_count = 0;
if($_SESSION['role'] === 'seller') {
    $ptq = mysqli_query($conn, "SELECT COUNT(*) c FROM transactions WHERE seller_id={$_SESSION['user_id']} AND status='pending'");
    $pending_tx_count = mysqli_fetch_assoc($ptq)['c'];
} elseif($_SESSION['role'] === 'midman') {
    $ptq = mysqli_query($conn, "SELECT COUNT(*) c FROM transactions WHERE midman_id={$_SESSION['user_id']} AND status='pending'");
    $pending_tx_count = mysqli_fetch_assoc($ptq)['c'];
}

$user_id = $_SESSION['user_id'];
$two_factor_enabled = is2FAEnabled($user_id);

// Get pending transactions count for sidebar badge
$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM transactions WHERE midman_id=$user_id AND status='pending'"))['c'] ?? 0;
$in_progress_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM transactions WHERE midman_id=$user_id AND status='in_progress'"))['c'] ?? 0;
$disputed_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM transactions WHERE midman_id=$user_id AND status='disputed'"))['c'] ?? 0;

// Get earnings summary
$summary = [];

// Total earned
$total = mysqli_query($conn, "SELECT SUM(amount) as total FROM earnings WHERE midman_id = $user_id AND status = 'paid'");
$summary['total'] = mysqli_fetch_assoc($total)['total'] ?? 0;

// Pending earnings
$pending = mysqli_query($conn, "SELECT SUM(amount) as total FROM earnings WHERE midman_id = $user_id AND status = 'pending'");
$summary['pending'] = mysqli_fetch_assoc($pending)['total'] ?? 0;

// This month
$month = mysqli_query($conn, "SELECT SUM(amount) as total FROM earnings 
                              WHERE midman_id = $user_id AND status = 'paid' 
                              AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
                              AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$summary['month'] = mysqli_fetch_assoc($month)['total'] ?? 0;

// Total transactions handled
$count = mysqli_query($conn, "SELECT COUNT(*) as count FROM earnings WHERE midman_id = $user_id");
$summary['count'] = mysqli_fetch_assoc($count)['count'] ?? 0;

// Get earnings history with transaction details
$earnings = mysqli_query($conn, "SELECT e.*, t.amount as transaction_amount, t.status as transaction_status,
                                 p.title as product_title, p.image_path,
                                 b.username as buyer_name, s.username as seller_name
                                 FROM earnings e
                                 JOIN transactions t ON e.transaction_id = t.id
                                 JOIN products p ON t.product_id = p.id
                                 JOIN users b ON t.buyer_id = b.id
                                 JOIN users s ON t.seller_id = s.id
                                 WHERE e.midman_id = $user_id
                                 ORDER BY e.created_at DESC");

$display_name = $_SESSION['full_name'] ?? $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Earnings — Trusted Midman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

        :root {
            /* warm dark base — same as all other pages */
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
            --gold-glow:  rgba(240,165,0,0.28);
            --teal:       #00d4aa;
            --teal-dim:   rgba(0,212,170,0.11);
            --red:        #ff4d6d;
            --red-dim:    rgba(255,77,109,0.12);
            --orange:     #ff9632;
            --orange-dim: rgba(255,150,50,0.12);
            --blue:       #4e9fff;
            --blue-dim:   rgba(78,159,255,0.12);
            --purple:     #a064ff;
            --purple-lt:  #be8fff;
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

        html { scroll-behavior:smooth; }
        body { font-family:var(--font-body); background:var(--bg); color:var(--text-warm); min-height:100vh; overflow-x:hidden; -webkit-font-smoothing:antialiased; }
        .layout { display:flex; min-height:100vh; }

        @keyframes fadeUp { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);} }

        /* ── SIDEBAR — purple accent for midman ── */
        .sidebar { width:var(--sidebar-w); background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh; z-index:100; transition:transform 0.35s cubic-bezier(.77,0,.18,1); }
        .sidebar::before { content:''; position:absolute; bottom:-80px; left:-80px; width:300px; height:300px; background:radial-gradient(circle,rgba(120,60,200,0.09) 0%,transparent 65%); pointer-events:none; }
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
        .logo-sub  { font-size:0.65rem; color:var(--purple); letter-spacing:0.12em; text-transform:uppercase; display:block; font-family:var(--font-body); font-weight:600; }
        .sidebar-nav { flex:1; padding:18px 10px; overflow-y:auto; position:relative; z-index:1; }
        .nav-label { font-size:0.65rem; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:var(--text-dim); padding:12px 12px 7px; }
        .nav-link { display:flex; align-items:center; gap:11px; padding:10px 13px; border-radius:var(--radius-sm); text-decoration:none; color:var(--text-muted); font-size:0.9rem; font-weight:500; margin-bottom:2px; transition:all 0.2s; position:relative; }
        .nav-link:hover { color:var(--text-warm); background:var(--surface2); }
        .nav-link.active { color:var(--purple); background:var(--purple-dim); border:1px solid rgba(160,100,255,0.14); }
        .nav-link.active::before { content:''; position:absolute; left:0; top:20%; bottom:20%; width:3px; background:var(--purple); border-radius:0 3px 3px 0; }
        .nav-icon { width:20px; text-align:center; font-size:0.9rem; flex-shrink:0; }
        .nav-badge { margin-left:auto; background:var(--red-dim); color:var(--red); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(255,77,109,0.15); }
        .security-badge { margin-left:auto; background:var(--teal-dim); color:var(--teal); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(0,212,170,0.15); }
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
        .content { padding:28px 32px; flex:1; }

        /* ── ALERTS ── */
        .alert { display:flex; align-items:center; gap:10px; padding:13px 16px; border-radius:var(--radius-sm); font-size:0.875rem; margin-bottom:20px; }
        .alert-success { background:var(--teal-dim);   color:var(--teal);   border:1px solid rgba(0,212,170,0.22); }
        .alert-error   { background:var(--red-dim);    color:#ff7090;       border:1px solid rgba(255,77,109,0.22); }
        .alert-warning { background:var(--orange-dim); color:var(--orange); border:1px solid rgba(255,150,50,0.22); }

        /* ── 2FA BANNER ── */
        .security-banner {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:16px 22px; margin-bottom:20px;
            display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px;
            opacity:0; animation:fadeUp 0.5s 0.05s ease forwards;
            position:relative;
        }
        .security-banner::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(160,100,255,0.2),transparent); }
        .sb-content { display:flex; align-items:center; gap:12px; }
        .sb-icon { width:40px; height:40px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0; }
        .sb-icon.off { background:var(--orange-dim); color:var(--orange); border:1px solid rgba(255,150,50,0.15); }
        .sb-icon.on  { background:var(--teal-dim);   color:var(--teal);   border:1px solid rgba(0,212,170,0.15); }
        .sb-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; margin-bottom:2px; }
        .sb-sub   { font-size:0.8rem; color:var(--text-muted); }
        .sb-btn {
            background:linear-gradient(135deg,var(--purple),#7040cc);
            color:white; padding:9px 18px; border-radius:30px;
            text-decoration:none; font-size:0.85rem; font-weight:600;
            transition:all 0.25s; white-space:nowrap;
            box-shadow:0 3px 12px var(--purple-glow);
        }
        .sb-btn:hover { transform:translateY(-2px); box-shadow:0 6px 20px var(--purple-glow); }

        /* ── STAT GRID ── */
        .stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:22px; }
        .stat-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:18px;
            display:flex; align-items:flex-start; gap:12px;
            opacity:0; animation:fadeUp 0.45s ease forwards;
            transition:border-color 0.25s, transform 0.25s;
            position:relative; overflow:hidden;
        }
        .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; opacity:0; transition:opacity 0.25s; }
        .stat-card:hover { border-color:var(--border2); transform:translateY(-3px); }
        .stat-card:hover::before { opacity:1; }
        .stat-card:nth-child(1) { animation-delay:.05s; } .stat-card:nth-child(1)::before { background:linear-gradient(90deg,var(--purple),transparent); }
        .stat-card:nth-child(2) { animation-delay:.10s; } .stat-card:nth-child(2)::before { background:linear-gradient(90deg,var(--orange),transparent); }
        .stat-card:nth-child(3) { animation-delay:.15s; } .stat-card:nth-child(3)::before { background:linear-gradient(90deg,var(--teal),transparent); }
        .stat-card:nth-child(4) { animation-delay:.20s; } .stat-card:nth-child(4)::before { background:linear-gradient(90deg,var(--gold),transparent); }

        .sc-icon { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:0.9rem; flex-shrink:0; border:1px solid transparent; }
        .si-purple { background:var(--purple-dim); color:var(--purple); border-color:rgba(160,100,255,0.14); }
        .si-orange { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.14); }
        .si-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .si-gold   { background:var(--gold-dim);   color:var(--gold);   border-color:rgba(240,165,0,0.14); }

        .sc-val { font-family:var(--font-head); font-size:1.35rem; font-weight:800; color:var(--text); line-height:1; letter-spacing:-0.01em; }
        .sc-lbl { font-size:0.7rem; color:var(--text-muted); margin-top:3px; }
        .sc-sub { font-size:0.65rem; color:var(--text-dim); margin-top:5px; display:flex; align-items:center; gap:3px; }

        /* ── TABLE PANEL ── */
        .panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden; margin-bottom:20px;
            opacity:0; animation:fadeUp 0.45s ease 0.3s forwards;
            position:relative;
        }
        .panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(160,100,255,0.15),transparent); z-index:1; }
        .panel-head { display:flex; align-items:center; justify-content:space-between; padding:15px 20px; border-bottom:1px solid var(--border); }
        .panel-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:8px; letter-spacing:-0.01em; }
        .pti { width:26px; height:26px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.72rem; border:1px solid transparent; }
        .pti-purple { background:var(--purple-dim); color:var(--purple); border-color:rgba(160,100,255,0.14); }
        .pti-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .count-chip { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); font-size:0.68rem; font-weight:700; padding:3px 9px; border-radius:10px; }

        /* ── TABLE ── */
        .earnings-table { width:100%; border-collapse:collapse; }
        .earnings-table thead tr { border-bottom:1px solid var(--border); }
        .earnings-table th { padding:11px 16px; font-size:0.67rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); text-align:left; background:var(--surface2); }
        .earnings-table th:last-child { text-align:right; }
        .earnings-table td { padding:13px 16px; border-bottom:1px solid var(--border); font-size:0.875rem; vertical-align:middle; }
        .earnings-table tr:last-child td { border-bottom:none; }
        .earnings-table tbody tr { transition:background 0.18s; }
        .earnings-table tbody tr:hover td { background:rgba(160,100,255,0.03); }

        .p-thumb { width:44px; height:36px; border-radius:8px; background:var(--surface2); border:1px solid var(--border); overflow:hidden; display:flex; align-items:center; justify-content:center; color:var(--text-dim); font-size:0.95rem; flex-shrink:0; }
        .p-thumb img { width:100%; height:100%; object-fit:cover; }
        .p-name  { font-weight:600; color:var(--text-warm); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:150px; }

        .amount-val { font-family:var(--font-head); font-weight:800; font-size:0.95rem; color:var(--text-warm); letter-spacing:-0.01em; }
        .fee-val    { font-family:var(--font-head); font-weight:700; font-size:0.9rem; color:var(--teal); letter-spacing:-0.01em; }
        .sbadge     { font-size:0.6rem; font-weight:800; text-transform:uppercase; padding:3px 9px; border-radius:20px; display:inline-flex; align-items:center; gap:4px; border:1px solid transparent; letter-spacing:0.04em; }
        .p-date     { font-size:0.76rem; color:var(--text-dim); }

        /* ── EMPTY ── */
        .empty { text-align:center; padding:50px 20px; }
        .empty-icon { width:64px; height:64px; background:var(--surface2); border:1px solid var(--border); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.6rem; color:var(--text-dim); margin:0 auto 12px; }
        .empty h4 { font-family:var(--font-head); color:var(--text-warm); margin-bottom:6px; letter-spacing:-0.01em; }
        .empty p  { font-size:0.84rem; color:var(--text-muted); }

        /* ── BOTTOM GRID (Info Cards) ── */
        .bottom-grid  { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-top:20px; }
        .info-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:18px 20px;
            opacity:0; animation:fadeUp 0.45s ease 0.42s forwards;
            position:relative;
            transition:transform 0.2s, border-color 0.2s;
        }
        .info-card:hover { transform:translateY(-2px); border-color:var(--border2); }
        .info-card h4 { font-family:var(--font-head); font-size:1rem; margin-bottom:12px; color:var(--purple); display:flex; align-items:center; gap:8px; }
        .info-card p { font-size:0.85rem; color:var(--text-muted); line-height:1.5; margin-bottom:8px; }
        .info-card strong { color:var(--text-warm); }

        /* ── RESPONSIVE ── */
        @media(max-width:1200px) { .stat-grid{grid-template-columns:repeat(2,1fr);} .bottom-grid{grid-template-columns:repeat(2,1fr);} }
        @media(max-width:1000px) { .bottom-grid{grid-template-columns:1fr;} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
        @media(max-width:640px)  { .stat-grid{grid-template-columns:1fr;} .earnings-table th:nth-child(2),.earnings-table td:nth-child(2){display:none;} }

        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }
    </style>
</head>
<body>
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <!-- SIDEBAR (identical to dashboard) -->
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
            <div class="nav-label">Midman</div>
            <a href="midman-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
            <a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-handshake"></i></span> Transactions
            <?php if($pending_tx_count > 0): ?><span class="nav-badge"><?php echo $pending_tx_count; ?></span><?php endif; ?></a>
            <a href="midman-earnings.php"  class="nav-link active"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
            <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span>Dispute Center<?php if($disputed_count>0): ?><span class="nav-badge"><?php echo $disputed_count; ?></span><?php endif; ?></a>
            <a href="verify-identity.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span> KYC Status</a>
            

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

    <!-- MAIN CONTENT -->
    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <span class="page-title">My Earnings</span>
            </div>
            <div class="active-dot">Active</div>
        </header>

        <div class="content">
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><i class="fas fa-triangle-exclamation"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <!-- 2FA BANNER (same as dashboard) -->
            <div class="security-banner">
                <div class="sb-content">
                    <div class="sb-icon <?php echo $two_factor_enabled ? 'on' : 'off'; ?>">
                        <i class="fas fa-<?php echo $two_factor_enabled ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    </div>
                    <div>
                        <div class="sb-title">Two-Factor Authentication</div>
                        <div class="sb-sub">
                            <?php if($two_factor_enabled): ?>
                                2FA is enabled — your account has enhanced protection.
                            <?php else: ?>
                                2FA is not enabled. Secure your account against unauthorized access.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <a href="setup-2fa.php" class="sb-btn">
                    <i class="fas fa-shield-alt"></i>
                    <?php echo $two_factor_enabled ? ' Manage 2FA' : ' Enable 2FA'; ?>
                </a>
            </div>

            <!-- Earnings Summary -->
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="sc-icon si-purple"><i class="fas fa-coins"></i></div>
                    <div>
                        <div class="sc-val">$<?php echo number_format($summary['total'], 2); ?></div>
                        <div class="sc-lbl">Total Earned</div>
                        <div class="sc-sub"><i class="fas fa-chart-line"></i> Lifetime</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-orange"><i class="fas fa-clock"></i></div>
                    <div>
                        <div class="sc-val">$<?php echo number_format($summary['pending'], 2); ?></div>
                        <div class="sc-lbl">Pending</div>
                        <div class="sc-sub"><i class="fas fa-hourglass-half"></i> Awaiting clearance</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-teal"><i class="fas fa-calendar-alt"></i></div>
                    <div>
                        <div class="sc-val">$<?php echo number_format($summary['month'], 2); ?></div>
                        <div class="sc-lbl">This Month</div>
                        <div class="sc-sub"><i class="fas fa-calendar-week"></i> <?php echo date('F Y'); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-gold"><i class="fas fa-handshake"></i></div>
                    <div>
                        <div class="sc-val"><?php echo $summary['count']; ?></div>
                        <div class="sc-lbl">Transactions</div>
                        <div class="sc-sub"><i class="fas fa-receipt"></i> Total handled</div>
                    </div>
                </div>
            </div>

            <!-- Earnings History Table -->
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title">
                        <div class="pti pti-purple"><i class="fas fa-list-ul"></i></div>
                        Earnings History
                    </div>
                    <span class="count-chip"><?php echo $summary['count']; ?> records</span>
                </div>

                <?php if(mysqli_num_rows($earnings) > 0): ?>
                <table class="earnings-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Parties</th>
                            <th>Transaction Amount</th>
                            <th>Your Fee</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($earning = mysqli_fetch_assoc($earnings)): ?>
                        <tr>
                            <td class="p-date"><?php echo date('M d, Y', strtotime($earning['created_at'])); ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div class="p-thumb">
                                        <?php if(!empty($earning['image_path'])): ?><img src="<?php echo htmlspecialchars($earning['image_path']); ?>" alt=""><?php else: ?><i class="fas fa-gamepad"></i><?php endif; ?>
                                    </div>
                                    <div class="p-name"><?php echo htmlspecialchars(substr($earning['product_title'],0,30)).(strlen($earning['product_title'])>30?'…':''); ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="parties">
                                    <div class="party-row">
                                        <span class="party-ava pa-buyer"><?php echo strtoupper(substr($earning['buyer_name'],0,2)); ?></span>
                                        <?php echo htmlspecialchars($earning['buyer_name']); ?>
                                    </div>
                                    <div style="font-size:0.62rem;color:var(--text-dim);padding-left:23px;">↓</div>
                                    <div class="party-row">
                                        <span class="party-ava pa-seller"><?php echo strtoupper(substr($earning['seller_name'],0,2)); ?></span>
                                        <?php echo htmlspecialchars($earning['seller_name']); ?>
                                    </div>
                                </div>
                            </td>
                            <td><div class="amount-val">$<?php echo number_format($earning['transaction_amount'], 2); ?></div></td>
                            <td><div class="fee-val">$<?php echo number_format($earning['amount'], 2); ?></div></td>
                            <td>
                                <?php if($earning['status'] == 'paid'): ?>
                                    <span class="sbadge" style="background:var(--teal-dim);color:var(--teal);"><i class="fas fa-check-circle"></i> Paid</span>
                                <?php else: ?>
                                    <span class="sbadge" style="background:var(--orange-dim);color:var(--orange);"><i class="fas fa-clock"></i> Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty">
                    <div class="empty-icon"><i class="fas fa-coins"></i></div>
                    <h4>No Earnings Yet</h4>
                    <p>Start handling transactions to earn service fees.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Fee Information Cards (same style as bottom panels) -->
            <div class="bottom-grid">
                <div class="info-card">
                    <h4><i class="fas fa-percent"></i> Service Fee</h4>
                    <p>You earn a <strong>5% service fee</strong> on every transaction you handle.</p>
                    <p><strong>Example:</strong> $100 transaction = $5 earnings</p>
                </div>
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
</script>
</body>
</html>
