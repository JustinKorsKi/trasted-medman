<?php
require_once '../includes/config.php';
require_once '../includes/verification-functions.php';



if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php'); exit();
}

// ── Handle approve / reject ──
if(isset($_GET['action']) && isset($_GET['id'])) {
    $app_id = intval($_GET['id']);
    $action = $_GET['action'];

    $app_query   = mysqli_query($conn, "SELECT * FROM midman_applications WHERE id=$app_id");
    $application = mysqli_fetch_assoc($app_query);

    if($action == 'approve') {
        $user_verification = getUserVerificationStatus($application['user_id']);
        if($user_verification['verification_level'] != 'verified') {
            $_SESSION['error'] = 'Cannot approve: User is not KYC verified.';
            header('Location: applications.php'); exit();
        }
        mysqli_query($conn,"UPDATE midman_applications SET status='approved', reviewed_by={$_SESSION['user_id']}, reviewed_at=NOW() WHERE id=$app_id");
        mysqli_query($conn,"UPDATE users SET role='midman', is_verified=1 WHERE id={$application['user_id']}");
        $_SESSION['success'] = 'Application approved. User is now a Midman.';
    } elseif($action == 'reject') {
        $notes = mysqli_real_escape_string($conn, $_GET['notes'] ?? '');
        mysqli_query($conn,"UPDATE midman_applications SET status='rejected', reviewed_by={$_SESSION['user_id']}, reviewed_at=NOW(), admin_notes='$notes' WHERE id=$app_id");
        $_SESSION['success'] = 'Application rejected.';
    }
    header('Location: applications.php'); exit();
}

// ── Data ──
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn,$_GET['status']) : 'pending';
$applications  = mysqli_query($conn,
    "SELECT a.*, u.username, u.email, u.full_name, u.verification_level
     FROM midman_applications a
     JOIN users u ON a.user_id = u.id
     WHERE a.status = '$status_filter'
     ORDER BY a.created_at DESC");

$counts = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(status='pending') as pending, SUM(status='approved') as approved, SUM(status='rejected') as rejected
     FROM midman_applications"));

// ── Sidebar badge counts ──
$pending_kyc   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM verification_requests WHERE status='pending'"))['c'];
$pending_apps  = $counts['pending'] ?? 0;
$open_disputes = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM disputes WHERE status='open'"))['c'];

$display_name = $_SESSION['full_name'] ?? $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Midman Applications — Trusted Midman Admin</title>
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
            --admin-lt:   #ff5a5a;
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
        .sidebar::before { content:''; position:absolute; bottom:-80px; left:-80px; width:300px; height:300px; background:radial-gradient(circle,rgba(180,30,30,0.1) 0%,transparent 65%); pointer-events:none; }
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
        .main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; }
        .topbar { position:sticky; top:0; z-index:50; background:rgba(15,12,8,0.88); backdrop-filter:blur(24px); border-bottom:1px solid var(--border); padding:0 32px; height:64px; display:flex; align-items:center; justify-content:space-between; }
        .topbar-left { display:flex; align-items:center; gap:16px; }
        .hamburger { display:none; background:none; border:none; color:var(--text-muted); font-size:1.1rem; cursor:pointer; padding:6px; border-radius:7px; transition:color 0.2s; }
        .hamburger:hover { color:var(--text-warm); }
        .page-title { font-family:var(--font-head); font-size:1.15rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .topbar-right { display:flex; align-items:center; gap:10px; }
        .back-btn { display:flex; align-items:center; gap:7px; padding:8px 14px; background:var(--surface2); border:1px solid var(--border2); border-radius:var(--radius-sm); color:var(--text-muted); text-decoration:none; font-size:0.84rem; font-weight:500; transition:all 0.2s; }
        .back-btn:hover { color:var(--text-warm); border-color:var(--border3); }
        .content { padding:28px 32px; flex:1; }

        /* ── ALERTS ── */
        .alert { display:flex; align-items:center; gap:10px; padding:13px 16px; border-radius:var(--radius-sm); font-size:0.875rem; margin-bottom:20px; }
        .alert-success { background:var(--teal-dim); color:var(--teal); border:1px solid rgba(0,212,170,0.22); }
        .alert-error   { background:var(--red-dim);  color:#ff7090;     border:1px solid rgba(255,77,109,0.22); }

        /* ── STAT STRIP ── */
        .stat-strip { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:22px; }
        .stat-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:20px;
            display:flex; align-items:center; gap:14px;
            opacity:0; animation:fadeUp 0.45s ease forwards;
            transition:border-color 0.25s, transform 0.25s;
            position:relative; overflow:hidden;
        }
        .stat-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; opacity:0; transition:opacity 0.25s; }
        .stat-card:hover { border-color:var(--border2); transform:translateY(-2px); }
        .stat-card:hover::before { opacity:1; }
        .stat-card:nth-child(1){animation-delay:.04s;} .stat-card:nth-child(1)::before{background:linear-gradient(90deg,var(--orange),transparent);}
        .stat-card:nth-child(2){animation-delay:.08s;} .stat-card:nth-child(2)::before{background:linear-gradient(90deg,var(--teal),transparent);}
        .stat-card:nth-child(3){animation-delay:.12s;} .stat-card:nth-child(3)::before{background:linear-gradient(90deg,var(--red),transparent);}
        .sc-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; border:1px solid transparent; }
        .si-orange { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.14); }
        .si-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .si-red    { background:var(--red-dim);    color:var(--red);    border-color:rgba(255,77,109,0.14); }
        .sc-val { font-family:var(--font-head); font-size:1.6rem; font-weight:800; color:var(--text); line-height:1; letter-spacing:-0.01em; }
        .sc-lbl { font-size:0.72rem; color:var(--text-muted); margin-top:3px; }

        /* ── TABS ── */
        .tab-strip {
            display:flex; gap:8px; margin-bottom:22px;
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:10px 14px;
            opacity:0; animation:fadeUp 0.45s ease 0.14s forwards;
        }
        .tab-link { display:flex; align-items:center; gap:7px; padding:8px 16px; border-radius:var(--radius-sm); text-decoration:none; color:var(--text-muted); font-size:0.875rem; font-weight:600; transition:all 0.2s; }
        .tab-link:hover { color:var(--text-warm); background:var(--surface2); }
        .tab-link.active { background:var(--admin-dim); color:var(--admin); border:1px solid rgba(224,53,53,0.14); }
        .tab-count { font-size:0.68rem; font-weight:800; padding:2px 7px; border-radius:10px; }
        .tab-link.active .tab-count { background:var(--admin); color:white; }
        .tab-link:not(.active) .tab-count { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }

        /* ── APP CARDS ── */
        .app-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius-lg); overflow:hidden; margin-bottom:18px;
            opacity:0; animation:fadeUp 0.45s ease forwards;
            position:relative;
        }
        .app-card::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(224,53,53,0.2),transparent); }
        .app-card:nth-child(1){animation-delay:.18s;} .app-card:nth-child(2){animation-delay:.23s;} .app-card:nth-child(3){animation-delay:.28s;}

        /* card head */
        .app-head { display:flex; align-items:center; justify-content:space-between; padding:20px 24px; border-bottom:1px solid var(--border); gap:16px; flex-wrap:wrap; }
        .app-user { display:flex; align-items:center; gap:14px; }
        .app-ava  { width:46px; height:46px; border-radius:50%; background:linear-gradient(135deg,var(--admin),#8b1a1a); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:1rem; color:white; flex-shrink:0; box-shadow:0 0 12px var(--admin-glow); }
        .app-name { font-family:var(--font-head); font-size:1.05rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; margin-bottom:3px; }
        .app-sub  { font-size:0.78rem; color:var(--text-muted); }
        .badge-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }

        /* badges */
        .sbadge { font-size:0.65rem; font-weight:700; text-transform:uppercase; padding:4px 10px; border-radius:20px; letter-spacing:0.05em; display:inline-flex; align-items:center; gap:5px; border:1px solid transparent; }
        .sb-pending    { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.2); }
        .sb-approved   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.2); }
        .sb-rejected   { background:var(--red-dim);    color:var(--red);    border-color:rgba(255,77,109,0.2); }
        .sb-verified   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.2); }
        .sb-unverified { background:var(--red-dim);    color:var(--red);    border-color:rgba(255,77,109,0.2); }
        .sb-kyc-pending{ background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.2); }

        /* body */
        .app-body { padding:20px 24px; }
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:0; }
        .info-item { background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); padding:14px 16px; }
        .info-label { font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); margin-bottom:6px; }
        .info-val { font-size:0.875rem; color:var(--text-warm); line-height:1.65; white-space:pre-line; }
        .info-val.date { font-family:var(--font-head); font-size:0.92rem; font-weight:600; letter-spacing:-0.01em; color:var(--text-warm); }

        /* admin notes */
        .admin-notes { margin:16px 24px 0; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); padding:14px 16px; }
        .admin-notes-label { font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); margin-bottom:6px; display:flex; align-items:center; gap:6px; }
        .admin-notes-label i { color:var(--admin); }
        .admin-notes-text { font-size:0.875rem; color:var(--text-muted); line-height:1.7; }

        /* action bar */
        .action-bar { display:flex; align-items:flex-start; gap:12px; padding:18px 24px; border-top:1px solid var(--border); flex-wrap:wrap; }
        .btn { display:inline-flex; align-items:center; gap:7px; padding:10px 20px; border:none; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.875rem; font-weight:600; cursor:pointer; text-decoration:none; transition:all 0.22s; letter-spacing:0.01em; }
        .btn-approve { background:var(--teal-dim); color:var(--teal); border:1px solid rgba(0,212,170,0.2); }
        .btn-approve:hover { background:var(--teal); color:#0f0c08; transform:translateY(-1px); box-shadow:0 4px 14px rgba(0,212,170,0.3); }
        .btn-reject  { background:var(--red-dim);  color:var(--red);  border:1px solid rgba(255,77,109,0.2); }
        .btn-reject:hover  { background:var(--red); color:white; transform:translateY(-1px); box-shadow:0 4px 14px rgba(255,77,109,0.3); }
        .btn-ghost   { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }
        .btn-ghost:hover   { color:var(--text-warm); border-color:var(--border3); }
        .btn-disabled { background:var(--surface2); color:var(--text-dim); border:1px solid var(--border); cursor:not-allowed; opacity:0.7; }

        /* not-verified warning */
        .not-verified-notice { display:flex; align-items:center; gap:8px; padding:10px 14px; background:var(--orange-dim); border:1px solid rgba(255,150,50,0.2); border-radius:var(--radius-sm); font-size:0.82rem; color:var(--orange); }

        /* reject expand */
        .reject-expand { display:none; flex:1; min-width:280px; }
        .reject-expand.visible { display:flex; flex-direction:column; gap:10px; }
        .reject-expand textarea {
            width:100%; padding:10px 13px; background:var(--surface2);
            border:1px solid var(--border); border-radius:var(--radius-sm);
            color:var(--text-warm); font-family:var(--font-body); font-size:0.875rem;
            resize:vertical; min-height:80px; outline:none; transition:border-color 0.2s;
        }
        .reject-expand textarea:focus { border-color:var(--red); box-shadow:0 0 0 3px rgba(255,77,109,0.1); }
        .reject-expand textarea::placeholder { color:var(--text-dim); }
        .reject-btn-row { display:flex; gap:8px; }

        /* ── EMPTY ── */
        .empty { text-align:center; padding:60px 24px; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-lg); opacity:0; animation:fadeUp 0.45s ease 0.18s forwards; }
        .empty-icon { width:80px; height:80px; background:var(--surface2); border:1px solid var(--border); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:2rem; color:var(--text-dim); margin:0 auto 18px; }
        .empty h3 { font-family:var(--font-head); font-size:1.2rem; color:var(--text-warm); margin-bottom:8px; letter-spacing:-0.01em; }
        .empty p  { font-size:0.875rem; color:var(--text-muted); }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:900px)  { .info-grid{grid-template-columns:1fr;} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
        @media(max-width:640px)  { .stat-strip{grid-template-columns:1fr;} .tab-strip{flex-wrap:wrap;} }
    </style>
</head>
<body>
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <!-- SIDEBAR — identical to admin dashboard -->
    <aside class="sidebar" id="sidebar">
    <a href="index.php" class="sidebar-logo">
    <div class="logo-icon">
        <img src="../images/logowhite.png" alt="Trusted Midman">
    </div>
    <div class="logo-text">
        Trusted Midman
        <span class="logo-sub">Admin Panel</span>
    </div>
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
            <a href="applications.php"  class="nav-link active">
                <span class="nav-icon"><i class="fas fa-user-check"></i></span> Midman Apps
                <?php if($pending_apps>0): ?><span class="nav-badge"><?php echo $pending_apps; ?></span><?php endif; ?>
            </a>
            <a href="disputes.php"      class="nav-link">
                <span class="nav-icon"><i class="fas fa-gavel"></i></span> Disputes
                <?php if($open_disputes>0): ?><span class="nav-badge"><?php echo $open_disputes; ?></span><?php endif; ?>
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
                <span class="page-title">Midman Applications</span>
            </div>
            <div class="topbar-right">
                <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
            </div>
        </header>

        <div class="content">

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <!-- STAT STRIP -->
            <div class="stat-strip">
                <div class="stat-card">
                    <div class="sc-icon si-orange"><i class="fas fa-clock"></i></div>
                    <div>
                        <div class="sc-val"><?php echo $counts['pending'] ?? 0; ?></div>
                        <div class="sc-lbl">Pending Review</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-teal"><i class="fas fa-handshake"></i></div>
                    <div>
                        <div class="sc-val"><?php echo $counts['approved'] ?? 0; ?></div>
                        <div class="sc-lbl">Approved</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-red"><i class="fas fa-circle-xmark"></i></div>
                    <div>
                        <div class="sc-val"><?php echo $counts['rejected'] ?? 0; ?></div>
                        <div class="sc-lbl">Rejected</div>
                    </div>
                </div>
            </div>

            <!-- TABS -->
            <div class="tab-strip">
                <a href="?status=pending"  class="tab-link <?php echo $status_filter=='pending' ?'active':''; ?>">
                    <i class="fas fa-clock" style="font-size:0.8rem;"></i> Pending
                    <span class="tab-count"><?php echo $counts['pending']??0; ?></span>
                </a>
                <a href="?status=approved" class="tab-link <?php echo $status_filter=='approved'?'active':''; ?>">
                    <i class="fas fa-handshake" style="font-size:0.8rem;"></i> Approved
                    <span class="tab-count"><?php echo $counts['approved']??0; ?></span>
                </a>
                <a href="?status=rejected" class="tab-link <?php echo $status_filter=='rejected'?'active':''; ?>">
                    <i class="fas fa-circle-xmark" style="font-size:0.8rem;"></i> Rejected
                    <span class="tab-count"><?php echo $counts['rejected']??0; ?></span>
                </a>
            </div>

            <!-- APPLICATION CARDS -->
            <?php if(mysqli_num_rows($applications) > 0):
                $idx = 0;
                while($app = mysqli_fetch_assoc($applications)):
                    $delay = 0.18 + $idx * 0.06; $idx++;

                    // App status badge
                    $app_badge = match($app['status']) { 'approved'=>'sb-approved','rejected'=>'sb-rejected',default=>'sb-pending' };
                    $app_icon  = match($app['status']) { 'approved'=>'fa-handshake','rejected'=>'fa-circle-xmark',default=>'fa-clock' };

                    // KYC badge
                    $vl = $app['verification_level'] ?? 'unverified';
                    $kyc_badge = match($vl) { 'verified'=>'sb-verified','pending'=>'sb-kyc-pending',default=>'sb-unverified' };
                    $kyc_icon  = match($vl) { 'verified'=>'fa-circle-check','pending'=>'fa-clock',default=>'fa-circle-xmark' };
            ?>
            <div class="app-card" style="animation-delay:<?php echo $delay; ?>s">

                <!-- HEAD -->
                <div class="app-head">
                    <div class="app-user">
                        <div class="app-ava"><?php echo strtoupper(substr($app['username'],0,2)); ?></div>
                        <div>
                            <div class="app-name"><?php echo htmlspecialchars($app['full_name']); ?></div>
                            <div class="app-sub">@<?php echo htmlspecialchars($app['username']); ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($app['email']); ?></div>
                        </div>
                    </div>
                    <div class="badge-row">
                        <!-- KYC status -->
                        <span class="sbadge <?php echo $kyc_badge; ?>">
                            <i class="fas <?php echo $kyc_icon; ?>" style="font-size:0.6rem;"></i>
                            KYC: <?php echo ucfirst($vl); ?>
                        </span>
                        <!-- Application status -->
                        <span class="sbadge <?php echo $app_badge; ?>">
                            <i class="fas <?php echo $app_icon; ?>" style="font-size:0.6rem;"></i>
                            <?php echo ucfirst($app['status']); ?>
                        </span>
                    </div>
                </div>

                <!-- BODY -->
                <div class="app-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Application Date</div>
                            <div class="info-val date"><?php echo date('M d, Y · g:i A', strtotime($app['created_at'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">KYC Verification</div>
                            <div class="info-val"><?php echo ucfirst($vl); ?><?php echo $vl!='verified'?' — User must complete KYC first':''; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Why become a Midman?</div>
                            <div class="info-val"><?php echo nl2br(htmlspecialchars($app['reason'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Experience</div>
                            <div class="info-val"><?php echo nl2br(htmlspecialchars($app['experience'] ?? 'No experience provided')); ?></div>
                        </div>
                        <?php if(!empty($app['references'])): ?>
                        <div class="info-item" style="grid-column:1/-1;">
                            <div class="info-label">References</div>
                            <div class="info-val"><?php echo nl2br(htmlspecialchars($app['references'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ADMIN NOTES (rejected) -->
                <?php if($app['status'] == 'rejected' && !empty($app['admin_notes'])): ?>
                <div class="admin-notes">
                    <div class="admin-notes-label"><i class="fas fa-comment-dots"></i> Admin Notes</div>
                    <div class="admin-notes-text"><?php echo nl2br(htmlspecialchars($app['admin_notes'])); ?></div>
                </div>
                <?php endif; ?>

                <!-- ACTIONS (pending only) -->
                <?php if($app['status'] == 'pending'): ?>
                <div class="action-bar">

                    <?php if($vl == 'verified'): ?>
                        <!-- Approve link -->
                        <a href="?action=approve&id=<?php echo $app['id']; ?>"
                           class="btn btn-approve"
                           onclick="return confirm('Approve this application? User will become a Midman.')">
                            <i class="fas fa-circle-check"></i> Approve Application
                        </a>
                    <?php else: ?>
                        <!-- Not verified warning -->
                        <div class="not-verified-notice">
                            <i class="fas fa-triangle-exclamation"></i>
                            User must complete KYC verification before approval
                        </div>
                    <?php endif; ?>

                    <!-- Reject trigger -->
                    <button class="btn btn-reject" onclick="showReject(<?php echo $app['id']; ?>)">
                        <i class="fas fa-circle-xmark"></i> Reject
                    </button>

                    <!-- Reject expand -->
                    <div class="reject-expand" id="reject-expand-<?php echo $app['id']; ?>">
                        <form method="GET" action="">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="id" value="<?php echo $app['id']; ?>">
                            <textarea name="notes" placeholder="Reason for rejection (optional)…"></textarea>
                            <div class="reject-btn-row">
                                <button type="submit" class="btn btn-reject"><i class="fas fa-circle-xmark"></i> Confirm Reject</button>
                                <button type="button" class="btn btn-ghost" onclick="hideReject(<?php echo $app['id']; ?>)">Cancel</button>
                            </div>
                        </form>
                    </div>

                </div>
                <?php endif; ?>

            </div>
            <?php endwhile; else: ?>
            <div class="empty">
                <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                <h3>No <?php echo ucfirst($status_filter); ?> Applications</h3>
                <p>There are no <?php echo $status_filter; ?> midman applications at the moment.</p>
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

    function showReject(id) { document.getElementById('reject-expand-' + id).classList.add('visible'); }
    function hideReject(id) { document.getElementById('reject-expand-' + id).classList.remove('visible'); }
</script>
</body>
</html>