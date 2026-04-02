<?php
$page_title = 'Manage Payouts - Admin';
require_once '../includes/config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php'); exit();
}

// ── Handle payout actions — LOGIC UNCHANGED ──
if(isset($_POST['action']) && isset($_POST['payout_id'])) {
    $payout_id   = intval($_POST['payout_id']);
    $admin_notes = mysqli_real_escape_string($conn, $_POST['admin_notes']);

    if($_POST['action'] == 'approve') {
        mysqli_query($conn, "UPDATE payout_requests SET status='approved', admin_notes='$admin_notes', processed_by={$_SESSION['user_id']} WHERE id=$payout_id");
        $_SESSION['success'] = 'Payout request approved.';
    } elseif($_POST['action'] == 'complete') {
        mysqli_query($conn, "UPDATE payout_requests SET status='completed', admin_notes='$admin_notes', processed_at=NOW(), processed_by={$_SESSION['user_id']} WHERE id=$payout_id");
        $_SESSION['success'] = 'Payout marked as completed.';
    } elseif($_POST['action'] == 'reject') {
        mysqli_query($conn, "UPDATE payout_requests SET status='rejected', admin_notes='$admin_notes', processed_by={$_SESSION['user_id']} WHERE id=$payout_id");
        $_SESSION['success'] = 'Payout request rejected.';
    }
    header('Location: payouts.php'); exit();
}

// ── Statistics — LOGIC UNCHANGED ──
$stats = [];
$pending_total        = mysqli_query($conn, "SELECT SUM(amount) as total FROM payout_requests WHERE status='pending'");
$stats['pending_total'] = mysqli_fetch_assoc($pending_total)['total'] ?? 0;
$stats['pending_count'] = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM payout_requests WHERE status='pending'"))['count'];
$stats['paid_total']    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payout_requests WHERE status='completed'"))['total'] ?? 0;

// ── Filter — LOGIC UNCHANGED ──
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$payouts = mysqli_query($conn,
    "SELECT p.*, u.username, u.email, u.full_name
     FROM payout_requests p
     JOIN users u ON p.midman_id = u.id
     WHERE p.status = '$status_filter'
     ORDER BY p.requested_at DESC");

// ── Sidebar badge counts ──
$pending_kyc   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM verification_requests WHERE status='pending'"))['c'];
$pending_apps  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM midman_applications WHERE status='pending'"))['c'];
$open_disputes = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM disputes WHERE status='open'"))['c'];
$display_name  = $_SESSION['full_name'] ?? $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payouts — Trusted Midman Admin</title>
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
            --admin:      #e03535;
            --admin-dim:  rgba(224,53,53,0.12);
            --admin-glow: rgba(224,53,53,0.28);
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
        body { font-family:var(--font-body); background:var(--bg); color:var(--text-warm); min-height:100vh; -webkit-font-smoothing:antialiased; }
        .layout { display:flex; min-height:100vh; }

        @keyframes fadeUp { from{opacity:0;transform:translateY(8px);}to{opacity:1;transform:translateY(0);} }

        /* ── SIDEBAR ── */
        .sidebar { width:var(--sidebar-w); background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh; z-index:100; transition:transform 0.35s cubic-bezier(.77,0,.18,1); }
        .sidebar::before { content:''; position:absolute; bottom:-80px; left:-80px; width:300px; height:300px; background:radial-gradient(circle,rgba(180,30,30,0.1) 0%,transparent 65%); pointer-events:none; }
        .sidebar-logo { display:flex; align-items:center; gap:12px; padding:26px 22px; text-decoration:none; border-bottom:1px solid var(--border); position:relative; z-index:1; }
        .logo-icon { width:38px; height:38px; background:linear-gradient(135deg,var(--admin),#b01e1e); border-radius:10px; display:flex; align-items:center; justify-content:center; color:white; font-size:16px; flex-shrink:0; box-shadow:0 0 20px var(--admin-glow); }
        .logo-text { font-family:var(--font-head); font-weight:700; font-size:1.1rem; color:var(--text); line-height:1.2; letter-spacing:-0.01em; }
        .logo-sub  { font-size:0.65rem; color:var(--admin); letter-spacing:0.12em; text-transform:uppercase; display:block; font-family:var(--font-body); font-weight:600; }
        .sidebar-nav { flex:1; padding:18px 10px; overflow-y:auto; position:relative; z-index:1; }
        .nav-label { font-size:0.65rem; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:var(--text-dim); padding:12px 12px 7px; }
        .nav-link { display:flex; align-items:center; gap:11px; padding:10px 13px; border-radius:var(--radius-sm); text-decoration:none; color:var(--text-muted); font-size:0.9rem; font-weight:500; margin-bottom:2px; transition:all 0.2s; position:relative; }
        .nav-link:hover { color:var(--text-warm); background:var(--surface2); }
        .nav-link.active { color:var(--admin); background:var(--admin-dim); border:1px solid rgba(224,53,53,0.14); }
        .nav-link.active::before { content:''; position:absolute; left:0; top:20%; bottom:20%; width:3px; background:var(--admin); border-radius:0 3px 3px 0; }
        .nav-icon { width:20px; text-align:center; font-size:0.9rem; flex-shrink:0; }
        .nav-badge { margin-left:auto; background:var(--red-dim); color:var(--red); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(255,77,109,0.2); }
        .sidebar-footer { padding:14px; border-top:1px solid var(--border); position:relative; z-index:1; }
        .user-pill { display:flex; align-items:center; gap:10px; padding:10px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); }
        .ava { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--admin),#8b1a1a); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.85rem; color:white; flex-shrink:0; box-shadow:0 0 10px var(--admin-glow); }
        .pill-name { font-size:0.875rem; font-weight:500; color:var(--text-warm); }
        .pill-role { font-size:0.68rem; color:var(--admin); text-transform:uppercase; letter-spacing:0.09em; }

        /* ── MAIN ── */
        .main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; min-height:100vh; }
        .topbar { position:sticky; top:0; z-index:50; background:rgba(15,12,8,0.88); backdrop-filter:blur(24px); border-bottom:1px solid var(--border); padding:0 28px; height:64px; display:flex; align-items:center; justify-content:space-between; }
        .topbar-left { display:flex; align-items:center; gap:16px; }
        .hamburger { display:none; background:none; border:none; color:var(--text-muted); font-size:1.1rem; cursor:pointer; padding:6px; border-radius:7px; }
        .page-title { font-family:var(--font-head); font-size:1.15rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .back-btn { display:flex; align-items:center; gap:7px; padding:8px 14px; background:var(--surface2); border:1px solid var(--border2); border-radius:var(--radius-sm); color:var(--text-muted); text-decoration:none; font-size:0.84rem; font-weight:500; transition:all 0.2s; }
        .back-btn:hover { color:var(--text-warm); border-color:var(--border3); }
        .content { padding:24px 28px; flex:1; }

        /* ── ALERTS ── */
        .alert { display:flex; align-items:center; gap:10px; padding:12px 16px; border-radius:var(--radius-sm); font-size:0.875rem; margin-bottom:20px; }
        .alert-success { background:var(--teal-dim); color:var(--teal); border:1px solid rgba(0,212,170,0.22); }
        .alert-error   { background:var(--red-dim);  color:#ff7090;     border:1px solid rgba(255,77,109,0.22); }

        /* ── STAT STRIP ── */
        .stat-strip { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:24px; }
        .stat-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:20px;
            display:flex; align-items:center; gap:14px;
            opacity:0; animation:fadeUp 0.4s ease forwards;
            position:relative; overflow:hidden;
            transition:border-color 0.22s, transform 0.22s;
        }
        .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; opacity:0; transition:opacity 0.22s; }
        .stat-card:hover { border-color:var(--border2); transform:translateY(-2px); }
        .stat-card:hover::before { opacity:1; }
        .stat-card:nth-child(1){ animation-delay:.04s; } .stat-card:nth-child(1)::before { background:linear-gradient(90deg,var(--orange),transparent); }
        .stat-card:nth-child(2){ animation-delay:.08s; } .stat-card:nth-child(2)::before { background:linear-gradient(90deg,var(--gold),transparent); }
        .stat-card:nth-child(3){ animation-delay:.12s; } .stat-card:nth-child(3)::before { background:linear-gradient(90deg,var(--teal),transparent); }
        .sc-icon { width:44px; height:44px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; border:1px solid transparent; }
        .si-orange { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.14); }
        .si-gold   { background:var(--gold-dim);   color:var(--gold);   border-color:rgba(240,165,0,0.14); }
        .si-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .sc-val { font-family:var(--font-head); font-size:1.55rem; font-weight:800; color:var(--text); line-height:1; letter-spacing:-0.01em; }
        .sc-lbl { font-size:0.72rem; color:var(--text-muted); margin-top:3px; }

        /* ── TAB STRIP ── */
        .tab-strip {
            display:flex; gap:8px; margin-bottom:22px;
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:10px 14px;
            opacity:0; animation:fadeUp 0.4s ease 0.14s forwards;
        }
        .tab-link { display:flex; align-items:center; gap:7px; padding:8px 16px; border-radius:var(--radius-sm); text-decoration:none; color:var(--text-muted); font-size:0.875rem; font-weight:600; transition:all 0.2s; }
        .tab-link:hover { color:var(--text-warm); background:var(--surface2); }
        .tab-link.active { background:var(--admin-dim); color:var(--admin); border:1px solid rgba(224,53,53,0.14); }
        .tab-icon { font-size:0.8rem; }

        /* ── PAYOUT CARDS ── */
        .payout-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius-lg); overflow:hidden;
            margin-bottom:16px;
            opacity:0; animation:fadeUp 0.4s ease forwards;
            position:relative;
        }
        .payout-card::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(224,53,53,0.18),transparent); }
        .payout-card:nth-child(1){animation-delay:.16s;} .payout-card:nth-child(2){animation-delay:.21s;} .payout-card:nth-child(3){animation-delay:.26s;}

        /* card header */
        .pc-head { display:flex; align-items:center; justify-content:space-between; padding:18px 22px; border-bottom:1px solid var(--border); flex-wrap:wrap; gap:12px; }
        .pc-user { display:flex; align-items:center; gap:12px; }
        .pc-ava  { width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,var(--purple),#7040cc); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.95rem; color:white; flex-shrink:0; box-shadow:0 0 10px var(--purple-dim); }
        .pc-name { font-family:var(--font-head); font-size:1.05rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; margin-bottom:2px; }
        .pc-sub  { font-size:0.75rem; color:var(--text-muted); }
        .pc-amount { font-family:var(--font-head); font-size:1.55rem; font-weight:800; color:var(--gold); letter-spacing:-0.01em; }

        /* card body */
        .pc-body { padding:18px 22px; }
        .pc-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px; }
        .pc-field { background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); padding:12px 14px; }
        .pc-field-label { font-size:0.64rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); margin-bottom:4px; }
        .pc-field-val   { font-size:0.875rem; color:var(--text-warm); line-height:1.55; }
        .pc-field-val.mono { font-family:monospace; font-size:0.82rem; word-break:break-all; }

        /* payment details box */
        .payment-box { background:var(--surface2); border:1px solid var(--border2); border-radius:var(--radius-sm); padding:14px 16px; margin-bottom:16px; }
        .payment-box-label { font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); margin-bottom:8px; display:flex; align-items:center; gap:6px; }
        .payment-box-label i { color:var(--gold); }
        .payment-box-val { font-family:monospace; font-size:0.84rem; color:var(--text-warm); line-height:1.7; white-space:pre-wrap; word-break:break-all; }

        /* ── FORMS / ACTIONS ── */
        .action-section { border-top:1px solid var(--border); padding:18px 22px; }
        .action-label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); margin-bottom:10px; }

        .notes-input {
            width:100%; padding:11px 13px;
            background:var(--surface2); border:1px solid var(--border);
            border-radius:var(--radius-sm); color:var(--text-warm);
            font-family:var(--font-body); font-size:0.875rem;
            resize:vertical; min-height:72px; outline:none;
            transition:border-color 0.2s;
            margin-bottom:12px;
        }
        .notes-input::placeholder { color:var(--text-dim); }
        .notes-input:focus { border-color:var(--admin); box-shadow:0 0 0 3px rgba(224,53,53,0.1); }

        .action-row { display:flex; gap:10px; flex-wrap:wrap; }

        .btn { display:inline-flex; align-items:center; gap:7px; padding:10px 18px; border:none; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.875rem; font-weight:600; cursor:pointer; text-decoration:none; transition:all 0.22s; letter-spacing:0.01em; }
        .btn-approve { background:var(--teal-dim); color:var(--teal); border:1px solid rgba(0,212,170,0.2); }
        .btn-approve:hover { background:var(--teal); color:#0f0c08; transform:translateY(-1px); box-shadow:0 4px 14px rgba(0,212,170,0.3); }
        .btn-complete { background:var(--purple-dim); color:var(--purple); border:1px solid rgba(160,100,255,0.2); }
        .btn-complete:hover { background:var(--purple); color:white; transform:translateY(-1px); box-shadow:0 4px 14px rgba(160,100,255,0.3); }
        .btn-reject  { background:var(--red-dim);  color:var(--red);  border:1px solid rgba(255,77,109,0.2); }
        .btn-reject:hover  { background:var(--red); color:white; transform:translateY(-1px); box-shadow:0 4px 14px rgba(255,77,109,0.3); }

        /* status badge */
        .sbadge { font-size:0.65rem; font-weight:700; text-transform:uppercase; padding:4px 10px; border-radius:20px; letter-spacing:0.05em; display:inline-flex; align-items:center; gap:5px; border:1px solid transparent; }
        .sb-pending   { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.2); }
        .sb-approved  { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.2); }
        .sb-completed { background:var(--gold-dim);   color:var(--gold);   border-color:rgba(240,165,0,0.2); }
        .sb-rejected  { background:var(--red-dim);    color:var(--red);    border-color:rgba(255,77,109,0.2); }

        /* method badge */
        .method-badge { display:inline-flex; align-items:center; gap:5px; font-size:0.7rem; font-weight:700; padding:3px 9px; border-radius:20px; background:var(--blue-dim); color:var(--blue); border:1px solid rgba(78,159,255,0.15); }

        /* ── EMPTY ── */
        .empty { text-align:center; padding:56px 24px; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); opacity:0; animation:fadeUp 0.4s ease 0.18s forwards; }
        .empty-icon { width:72px; height:72px; background:var(--surface2); border:1px solid var(--border); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.8rem; color:var(--text-dim); margin:0 auto 16px; }
        .empty h3 { font-family:var(--font-head); font-size:1.2rem; color:var(--text-warm); margin-bottom:6px; letter-spacing:-0.01em; }
        .empty p  { font-size:0.875rem; color:var(--text-muted); }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:900px)  { .stat-strip{grid-template-columns:1fr 1fr;} .pc-grid{grid-template-columns:1fr;} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:16px;} }
        @media(max-width:540px)  { .stat-strip{grid-template-columns:1fr;} .tab-strip{flex-wrap:wrap;} }
    </style>
</head>
<body>
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <!-- SIDEBAR — identical admin sidebar -->
    <aside class="sidebar" id="sidebar">
        <a href="../index.php" class="sidebar-logo">
            <div class="logo-icon"><i class="fas fa-shield-halved"></i></div>
            <div class="logo-text">Trusted Midman <span class="logo-sub">Admin Panel</span></div>
        </a>
        <nav class="sidebar-nav">
            <div class="nav-label">Overview</div>
            <a href="dashboard.php"     class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
            <a href="charts.php"        class="nav-link"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span> Reports</a>

            <div class="nav-label" style="margin-top:10px;">Management</div>
            <a href="users.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-users"></i></span> Users</a>
            <a href="transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-arrows-left-right"></i></span> Transactions</a>
            <a href="verifications.php" class="nav-link">
                <span class="nav-icon"><i class="fas fa-id-card"></i></span> KYC Verifications
                <?php if($pending_kyc>0): ?><span class="nav-badge"><?php echo $pending_kyc; ?></span><?php endif; ?>
            </a>
            <a href="applications.php"  class="nav-link">
                <span class="nav-icon"><i class="fas fa-user-check"></i></span> Midman Apps
                <?php if($pending_apps>0): ?><span class="nav-badge"><?php echo $pending_apps; ?></span><?php endif; ?>
            </a>
            <a href="disputes.php"      class="nav-link">
                <span class="nav-icon"><i class="fas fa-gavel"></i></span> Disputes
                <?php if($open_disputes>0): ?><span class="nav-badge"><?php echo $open_disputes; ?></span><?php endif; ?>
            </a>
            <a href="payouts.php"       class="nav-link active">
                <span class="nav-icon"><i class="fas fa-wallet"></i></span> Payouts
                <?php if($stats['pending_count']>0): ?><span class="nav-badge"><?php echo $stats['pending_count']; ?></span><?php endif; ?>
            </a>

            <div class="nav-label" style="margin-top:10px;">System</div>
            <a href="settings.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-gear"></i></span> Settings</a>
            <a href="../profile.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> My Profile</a>
            <a href="../logout.php"     class="nav-link" style="color:var(--text-dim);margin-top:6px;"><span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="ava"><?php echo strtoupper(substr($_SESSION['username'],0,2)); ?></div>
                <div>
                    <div class="pill-name"><?php echo htmlspecialchars($display_name); ?></div>
                    <div class="pill-role">Administrator</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <span class="page-title">Manage Payouts</span>
            </div>
            <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
        </header>

        <div class="content">

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <!-- STAT STRIP -->
            <div class="stat-strip">
                <div class="stat-card">
                    <div class="sc-icon si-orange"><i class="fas fa-clock"></i></div>
                    <div>
                        <div class="sc-val"><?php echo $stats['pending_count']; ?></div>
                        <div class="sc-lbl">Pending Requests</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-gold"><i class="fas fa-coins"></i></div>
                    <div>
                        <div class="sc-val">$<?php echo number_format($stats['pending_total'],2); ?></div>
                        <div class="sc-lbl">Pending Amount</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-teal"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="sc-val">$<?php echo number_format($stats['paid_total'],2); ?></div>
                        <div class="sc-lbl">Total Paid Out</div>
                    </div>
                </div>
            </div>

            <!-- TAB STRIP -->
            <div class="tab-strip">
                <a href="?status=pending"   class="tab-link <?php echo $status_filter=='pending'   ?'active':''; ?>">
                    <i class="fas fa-clock tab-icon"></i> Pending
                </a>
                <a href="?status=approved"  class="tab-link <?php echo $status_filter=='approved'  ?'active':''; ?>">
                    <i class="fas fa-circle-check tab-icon"></i> Approved
                </a>
                <a href="?status=completed" class="tab-link <?php echo $status_filter=='completed' ?'active':''; ?>">
                    <i class="fas fa-coins tab-icon"></i> Completed
                </a>
                <a href="?status=rejected"  class="tab-link <?php echo $status_filter=='rejected'  ?'active':''; ?>">
                    <i class="fas fa-circle-xmark tab-icon"></i> Rejected
                </a>
            </div>

            <!-- PAYOUT CARDS -->
            <?php if(mysqli_num_rows($payouts) > 0):
                $idx = 0;
                while($payout = mysqli_fetch_assoc($payouts)):
                $delay = 0.16 + $idx * 0.06; $idx++;

                $status_badge_cls = match($payout['status']) {
                    'approved'  => 'sb-approved',
                    'completed' => 'sb-completed',
                    'rejected'  => 'sb-rejected',
                    default     => 'sb-pending'
                };
                $status_icon = match($payout['status']) {
                    'approved'  => 'fa-circle-check',
                    'completed' => 'fa-coins',
                    'rejected'  => 'fa-circle-xmark',
                    default     => 'fa-clock'
                };
            ?>
            <div class="payout-card" style="animation-delay:<?php echo $delay; ?>s">

                <!-- HEAD -->
                <div class="pc-head">
                    <div class="pc-user">
                        <div class="pc-ava"><?php echo strtoupper(substr($payout['username'],0,2)); ?></div>
                        <div>
                            <div class="pc-name"><?php echo htmlspecialchars($payout['full_name']); ?></div>
                            <div class="pc-sub">@<?php echo htmlspecialchars($payout['username']); ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($payout['email']); ?></div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <span class="sbadge <?php echo $status_badge_cls; ?>">
                            <i class="fas <?php echo $status_icon; ?>" style="font-size:0.58rem;"></i>
                            <?php echo ucfirst($payout['status']); ?>
                        </span>
                        <div class="pc-amount">$<?php echo number_format($payout['amount'],2); ?></div>
                    </div>
                </div>

                <!-- BODY -->
                <div class="pc-body">
                    <div class="pc-grid">
                        <div class="pc-field">
                            <div class="pc-field-label">Payment Method</div>
                            <div class="pc-field-val">
                                <span class="method-badge">
                                    <i class="fas fa-credit-card" style="font-size:0.65rem;"></i>
                                    <?php echo ucfirst(str_replace('_',' ',$payout['payment_method'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="pc-field">
                            <div class="pc-field-label">Requested On</div>
                            <div class="pc-field-val"><?php echo date('M d, Y · g:i A', strtotime($payout['requested_at'])); ?></div>
                        </div>
                        <?php if(!empty($payout['admin_notes'])): ?>
                        <div class="pc-field" style="grid-column:1/-1;">
                            <div class="pc-field-label">Admin Notes</div>
                            <div class="pc-field-val"><?php echo nl2br(htmlspecialchars($payout['admin_notes'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Payment details -->
                    <div class="payment-box">
                        <div class="payment-box-label"><i class="fas fa-wallet"></i> Payment Details</div>
                        <div class="payment-box-val"><?php echo htmlspecialchars($payout['payment_details']); ?></div>
                    </div>

                    <!-- ACTIONS — LOGIC UNCHANGED -->
                    <?php if($payout['status'] == 'pending'): ?>
                    <div class="action-section" style="padding:0;">
                        <div class="action-label" style="margin-bottom:10px;">Admin Actions</div>
                        <div style="display:flex;gap:14px;flex-wrap:wrap;align-items:flex-start;">
                            <!-- Approve -->
                            <form method="POST" style="flex:1;min-width:200px;">
                                <input type="hidden" name="payout_id" value="<?php echo $payout['id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <textarea name="admin_notes" class="notes-input" placeholder="Approval notes (optional)…"></textarea>
                                <button type="submit" class="btn btn-approve">
                                    <i class="fas fa-circle-check"></i> Approve
                                </button>
                            </form>
                            <!-- Reject -->
                            <form method="POST" style="flex:1;min-width:200px;">
                                <input type="hidden" name="payout_id" value="<?php echo $payout['id']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <textarea name="admin_notes" class="notes-input" placeholder="Rejection reason (required)…" required></textarea>
                                <button type="submit" class="btn btn-reject">
                                    <i class="fas fa-circle-xmark"></i> Reject
                                </button>
                            </form>
                        </div>
                    </div>

                    <?php elseif($payout['status'] == 'approved'): ?>
                    <div class="action-section" style="padding:0;">
                        <div class="action-label" style="margin-bottom:10px;">Mark as Completed</div>
                        <form method="POST">
                            <input type="hidden" name="payout_id" value="<?php echo $payout['id']; ?>">
                            <input type="hidden" name="action" value="complete">
                            <textarea name="admin_notes" class="notes-input" placeholder="Completion notes (optional)…"></textarea>
                            <button type="submit" class="btn btn-complete">
                                <i class="fas fa-coins"></i> Mark as Completed
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
            <?php endwhile;
            else: ?>
            <div class="empty">
                <div class="empty-icon"><i class="fas fa-wallet"></i></div>
                <h3>No <?php echo ucfirst($status_filter); ?> Payouts</h3>
                <p>There are no <?php echo $status_filter; ?> payout requests at the moment.</p>
            </div>
            <?php endif; ?>

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