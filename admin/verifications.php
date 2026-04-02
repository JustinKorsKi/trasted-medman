<?php
require_once '../includes/config.php';
require_once '../includes/verification-functions.php';


if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php'); exit();
}

// ── Handle approve / reject ──
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $request_id  = intval($_POST['request_id']);
    $admin_notes = mysqli_real_escape_string($conn, $_POST['admin_notes'] ?? '');

    if($_POST['action'] == 'approve') {
        mysqli_query($conn,"UPDATE verification_requests SET status='approved', reviewed_at=NOW(), reviewed_by={$_SESSION['user_id']}, admin_notes='$admin_notes' WHERE id=$request_id");
        $req = mysqli_fetch_assoc(mysqli_query($conn,"SELECT user_id FROM verification_requests WHERE id=$request_id"));
        mysqli_query($conn,"UPDATE users SET verification_level='verified', verification_reviewed_at=NOW(), verification_notes='$admin_notes', verified_by={$_SESSION['user_id']} WHERE id={$req['user_id']}");
        $_SESSION['success'] = 'Verification request approved. User is now verified.';
    } elseif($_POST['action'] == 'reject') {
        mysqli_query($conn,"UPDATE verification_requests SET status='rejected', reviewed_at=NOW(), reviewed_by={$_SESSION['user_id']}, admin_notes='$admin_notes' WHERE id=$request_id");
        $req = mysqli_fetch_assoc(mysqli_query($conn,"SELECT user_id FROM verification_requests WHERE id=$request_id"));
        mysqli_query($conn,"UPDATE users SET verification_level='rejected', verification_reviewed_at=NOW(), verification_notes='$admin_notes', verified_by={$_SESSION['user_id']} WHERE id={$req['user_id']}");
        $_SESSION['success'] = 'Verification request rejected.';
    }
    header('Location: verifications.php'); exit();
}

// ── Filters & data ──
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'pending';
$requests = mysqli_query($conn,
    "SELECT vr.*, u.username, u.email, u.full_name
     FROM verification_requests vr
     JOIN users u ON vr.user_id = u.id
     WHERE vr.status = '$status_filter'
     ORDER BY vr.submitted_at DESC");

$counts = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(status='pending') as pending, SUM(status='approved') as approved, SUM(status='rejected') as rejected
     FROM verification_requests"));

// ── Sidebar badges ──
$pending_kyc   = $counts['pending'] ?? 0;
$pending_apps  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM midman_applications WHERE status='pending'"))['c'];
$open_disputes = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM disputes WHERE status='open'"))['c'];

$display_name = $_SESSION['full_name'] ?? $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KYC Verifications — Trusted Midman Admin</title>
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

        /* ── REQUEST CARDS ── */
        .req-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius-lg); overflow:hidden; margin-bottom:18px;
            opacity:0; animation:fadeUp 0.45s ease forwards;
            position:relative;
        }
        .req-card::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(224,53,53,0.2),transparent); }
        .req-card:nth-child(1){animation-delay:.18s;} .req-card:nth-child(2){animation-delay:.23s;} .req-card:nth-child(3){animation-delay:.28s;}

        /* card header */
        .req-head { display:flex; align-items:center; justify-content:space-between; padding:20px 24px; border-bottom:1px solid var(--border); gap:16px; flex-wrap:wrap; }
        .req-user { display:flex; align-items:center; gap:14px; }
        .req-ava { width:46px; height:46px; border-radius:50%; background:linear-gradient(135deg,var(--admin),#8b1a1a); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:1rem; color:white; flex-shrink:0; box-shadow:0 0 12px var(--admin-glow); }
        .req-name { font-family:var(--font-head); font-size:1.05rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; margin-bottom:3px; }
        .req-sub  { font-size:0.78rem; color:var(--text-muted); }

        /* status badge */
        .sbadge { font-size:0.65rem; font-weight:700; text-transform:uppercase; padding:4px 10px; border-radius:20px; letter-spacing:0.05em; display:inline-flex; align-items:center; gap:5px; border:1px solid transparent; }
        .sb-pending  { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.2); }
        .sb-approved { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.2); }
        .sb-rejected { background:var(--red-dim);    color:var(--red);    border-color:rgba(255,77,109,0.2); }

        /* info grid */
        .req-body { padding:20px 24px; }
        .info-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:20px; }
        .info-item { background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); padding:13px 15px; }
        .info-label { font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); margin-bottom:5px; }
        .info-val { font-size:0.9rem; font-weight:600; color:var(--text-warm); }

        /* document preview */
        .doc-section { margin-bottom:18px; }
        .doc-label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); margin-bottom:10px; display:flex; align-items:center; gap:7px; }
        .doc-label i { color:var(--admin); }
        .doc-frame { background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; text-align:center; padding:16px; }
        .doc-frame img { max-width:100%; max-height:320px; border-radius:var(--radius-sm); border:1px solid var(--border2); object-fit:contain; }
        .doc-link { display:inline-flex; align-items:center; gap:8px; padding:10px 20px; background:var(--admin-dim); color:var(--admin); border:1px solid rgba(224,53,53,0.2); border-radius:var(--radius-sm); text-decoration:none; font-size:0.875rem; font-weight:600; transition:all 0.2s; }
        .doc-link:hover { background:var(--admin); color:white; }

        /* action bar */
        .action-bar { display:flex; align-items:flex-start; gap:12px; padding:18px 24px; border-top:1px solid var(--border); flex-wrap:wrap; }
        .btn { display:inline-flex; align-items:center; gap:7px; padding:10px 20px; border:none; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.875rem; font-weight:600; cursor:pointer; text-decoration:none; transition:all 0.22s; letter-spacing:0.01em; }
        .btn-approve { background:var(--teal-dim); color:var(--teal); border:1px solid rgba(0,212,170,0.2); }
        .btn-approve:hover { background:var(--teal); color:#0f0c08; transform:translateY(-1px); box-shadow:0 4px 14px rgba(0,212,170,0.3); }
        .btn-reject  { background:var(--red-dim);  color:var(--red);  border:1px solid rgba(255,77,109,0.2); }
        .btn-reject:hover  { background:var(--red); color:white; transform:translateY(-1px); box-shadow:0 4px 14px rgba(255,77,109,0.3); }
        .btn-ghost   { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }
        .btn-ghost:hover   { color:var(--text-warm); border-color:var(--border3); }

        /* reject expand form */
        .reject-expand { display:none; flex:1; min-width:260px; }
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

        /* admin notes (reviewed) */
        .admin-notes { margin:0 24px 18px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); padding:14px 16px; }
        .admin-notes-label { font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); margin-bottom:6px; }
        .admin-notes-text { font-size:0.875rem; color:var(--text-muted); line-height:1.7; }

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

        @media(max-width:1000px) { .info-grid{grid-template-columns:1fr 1fr;} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
        @media(max-width:640px)  { .stat-strip{grid-template-columns:1fr;} .info-grid{grid-template-columns:1fr;} .tab-strip{flex-wrap:wrap;} }
    </style>
</head>
<body>
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <!-- SIDEBAR — identical to admin dashboard -->
    <aside class="sidebar" id="sidebar">
           <a href="dashboard.php" class="sidebar-logo">
        <div class="logo-icon">
            <img src="../images/logowhite.png" alt="Trusted Midman" style="width:100%; height:100%; object-fit:cover;">
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
            <a href="verifications.php" class="nav-link active">
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
                <span class="page-title">KYC Verifications</span>
            </div>
            <div class="topbar-right">
                <a href="dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Dashboard</a>
            </div>
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
                        <div class="sc-val"><?php echo $counts['pending'] ?? 0; ?></div>
                        <div class="sc-lbl">Pending Review</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-teal"><i class="fas fa-circle-check"></i></div>
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
                    <i class="fas fa-circle-check" style="font-size:0.8rem;"></i> Approved
                    <span class="tab-count"><?php echo $counts['approved']??0; ?></span>
                </a>
                <a href="?status=rejected" class="tab-link <?php echo $status_filter=='rejected'?'active':''; ?>">
                    <i class="fas fa-circle-xmark" style="font-size:0.8rem;"></i> Rejected
                    <span class="tab-count"><?php echo $counts['rejected']??0; ?></span>
                </a>
            </div>

            <!-- REQUEST CARDS -->
            <?php if(mysqli_num_rows($requests) > 0):
                $idx = 0;
                while($req = mysqli_fetch_assoc($requests)):
                    $delay = 0.18 + $idx * 0.06; $idx++;
                    $badge_class = match($req['status']) { 'approved'=>'sb-approved','rejected'=>'sb-rejected', default=>'sb-pending' };
                    $badge_icon  = match($req['status']) { 'approved'=>'fa-circle-check','rejected'=>'fa-circle-xmark', default=>'fa-clock' };
            ?>
            <div class="req-card" style="animation-delay:<?php echo $delay; ?>s">

                <!-- HEAD -->
                <div class="req-head">
                    <div class="req-user">
                        <div class="req-ava"><?php echo strtoupper(substr($req['username'],0,2)); ?></div>
                        <div>
                            <div class="req-name"><?php echo htmlspecialchars($req['full_name']); ?></div>
                            <div class="req-sub">@<?php echo htmlspecialchars($req['username']); ?> &nbsp;·&nbsp; <?php echo htmlspecialchars($req['email']); ?></div>
                        </div>
                    </div>
                    <span class="sbadge <?php echo $badge_class; ?>">
                        <i class="fas <?php echo $badge_icon; ?>" style="font-size:0.6rem;"></i>
                        <?php echo ucfirst($req['status']); ?>
                    </span>
                </div>

                <!-- BODY -->
                <div class="req-body">

                    <!-- INFO GRID -->
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Document Type</div>
                            <div class="info-val"><?php echo ucfirst(str_replace('_',' ',$req['document_type'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Document Number</div>
                            <div class="info-val"><?php echo htmlspecialchars($req['document_number']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Submitted</div>
                            <div class="info-val"><?php echo date('M d, Y · g:i A', strtotime($req['submitted_at'])); ?></div>
                        </div>
                    </div>

                    <!-- DOCUMENT IMAGE -->
                    <div class="doc-section">
                        <div class="doc-label"><i class="fas fa-id-card"></i> Document Image</div>
                        <div class="doc-frame">
                            <?php
                            $ext = strtolower(pathinfo($req['document_file'], PATHINFO_EXTENSION));
                            if(in_array($ext, ['jpg','jpeg','png','webp'])): ?>
                                <img src="../<?php echo htmlspecialchars($req['document_file']); ?>" alt="Document">
                            <?php else: ?>
                                <a href="../<?php echo htmlspecialchars($req['document_file']); ?>" target="_blank" class="doc-link">
                                    <i class="fas fa-file-pdf"></i> View PDF Document
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- SELFIE -->
                    <?php if(!empty($req['selfie_file'])): ?>
                    <div class="doc-section">
                        <div class="doc-label"><i class="fas fa-camera"></i> Selfie with ID</div>
                        <div class="doc-frame">
                            <img src="../<?php echo htmlspecialchars($req['selfie_file']); ?>" alt="Selfie with ID">
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- /req-body -->

                <!-- ADMIN NOTES (reviewed) -->
                <?php if($req['status'] != 'pending' && !empty($req['admin_notes'])): ?>
                <div class="admin-notes">
                    <div class="admin-notes-label">Admin Notes</div>
                    <div class="admin-notes-text"><?php echo nl2br(htmlspecialchars($req['admin_notes'])); ?></div>
                </div>
                <?php endif; ?>

                <!-- ACTIONS (pending only) -->
                <?php if($req['status'] == 'pending'): ?>
                <div class="action-bar" id="action-bar-<?php echo $req['id']; ?>">

                    <!-- Approve form -->
                    <form method="POST" style="display:contents;">
                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="admin_notes" value="">
                        <button type="submit" class="btn btn-approve" onclick="return confirm('Approve this verification request?')">
                            <i class="fas fa-circle-check"></i> Approve
                        </button>
                    </form>

                    <!-- Reject trigger -->
                    <button class="btn btn-reject" onclick="showReject(<?php echo $req['id']; ?>)">
                        <i class="fas fa-circle-xmark"></i> Reject
                    </button>

                    <!-- Reject expand form -->
                    <div class="reject-expand" id="reject-expand-<?php echo $req['id']; ?>">
                        <form method="POST">
                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                            <input type="hidden" name="action" value="reject">
                            <textarea name="admin_notes" placeholder="Reason for rejection (optional)…"></textarea>
                            <div class="reject-btn-row">
                                <button type="submit" class="btn btn-reject"><i class="fas fa-circle-xmark"></i> Confirm Reject</button>
                                <button type="button" class="btn btn-ghost" onclick="hideReject(<?php echo $req['id']; ?>)">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

            </div>
            <?php endwhile; else: ?>
            <div class="empty">
                <div class="empty-icon"><i class="fas fa-id-card"></i></div>
                <h3>No <?php echo ucfirst($status_filter); ?> Requests</h3>
                <p>There are no <?php echo $status_filter; ?> KYC submissions at the moment.</p>
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

    function showReject(id) {
        document.getElementById('reject-expand-' + id).classList.add('visible');
    }
    function hideReject(id) {
        document.getElementById('reject-expand-' + id).classList.remove('visible');
    }
</script>
</body>
</html>