<?php
require_once '../includes/config.php';
require_once '../includes/pagination.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php'); exit();
}

// ── Actions ──
if(isset($_GET['action']) && isset($_GET['id'])) {
    $uid = intval($_GET['id']);
    switch($_GET['action']) {
        case 'suspend':
            mysqli_query($conn,"UPDATE users SET is_active=0 WHERE id=$uid");
            $_SESSION['success'] = 'User suspended successfully.'; break;
        case 'activate':
            mysqli_query($conn,"UPDATE users SET is_active=1 WHERE id=$uid");
            $_SESSION['success'] = 'User activated successfully.'; break;
        case 'make_midman':
            mysqli_query($conn,"UPDATE users SET role='midman',is_verified=1 WHERE id=$uid");
            $_SESSION['success'] = 'User promoted to Midman.'; break;
        case 'delete':
            $chk = mysqli_query($conn,"SELECT id FROM transactions WHERE buyer_id=$uid OR seller_id=$uid LIMIT 1");
            if(mysqli_num_rows($chk) > 0) {
                $_SESSION['error'] = 'Cannot delete user with existing transactions.';
            } else {
                mysqli_query($conn,"DELETE FROM users WHERE id=$uid");
                $_SESSION['success'] = 'User deleted successfully.';
            }
            break;
    }
    header('Location: users.php'); exit();
}

// ── Filters ──
$search        = isset($_GET['search']) ? mysqli_real_escape_string($conn,$_GET['search']) : '';
$role_filter   = isset($_GET['role'])   ? mysqli_real_escape_string($conn,$_GET['role'])   : '';
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn,$_GET['status']) : '';

$query = "SELECT * FROM users WHERE 1=1";
if($search)        $query .= " AND (username LIKE '%$search%' OR email LIKE '%$search%' OR full_name LIKE '%$search%')";
if($role_filter)   $query .= " AND role='$role_filter'";
if($status_filter=='active')    $query .= " AND is_active=1";
if($status_filter=='suspended') $query .= " AND is_active=0";
$query .= " ORDER BY created_at DESC";

$page       = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page   = 20;
$pagination = paginateQuery($conn, $query, $page, $per_page);
$users      = $pagination['results'];
$total_pages  = $pagination['total_pages'];
$current_page = $pagination['current_page'];

// ── Quick stats ──
$stat_total    = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users"))['c'];
$stat_active   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users WHERE is_active=1"))['c'];
$stat_susp     = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users WHERE is_active=0"))['c'];
$stat_midmen   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users WHERE role='midman'"))['c'];

// ── Badge counts for sidebar ──
$pending_kyc   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM verification_requests WHERE status='pending'"))['c'];
$pending_apps  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM midman_applications WHERE status='pending'"))['c'];
$open_disputes = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM disputes WHERE status='open'"))['c'];

$display_name = $_SESSION['full_name'] ?? $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users — Trusted Midman Admin</title>
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
        .notif-btn { width:36px; height:36px; border-radius:var(--radius-sm); background:var(--surface2); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; color:var(--text-muted); cursor:pointer; transition:all 0.2s; }
        .notif-btn:hover { border-color:rgba(224,53,53,0.25); color:var(--admin); background:var(--admin-dim); }
        .content { padding:28px 32px; flex:1; }

        /* ── ALERTS ── */
        .alert { display:flex; align-items:center; gap:10px; padding:13px 16px; border-radius:var(--radius-sm); font-size:0.875rem; margin-bottom:20px; }
        .alert-success { background:var(--teal-dim); color:var(--teal); border:1px solid rgba(0,212,170,0.22); }
        .alert-error   { background:var(--red-dim);  color:#ff7090;     border:1px solid rgba(255,77,109,0.22); }

        /* ── STAT STRIP ── */
        .stat-strip { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
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
        .stat-card:nth-child(1){animation-delay:.04s;} .stat-card:nth-child(1)::before{background:linear-gradient(90deg,var(--admin),transparent);}
        .stat-card:nth-child(2){animation-delay:.08s;} .stat-card:nth-child(2)::before{background:linear-gradient(90deg,var(--teal),transparent);}
        .stat-card:nth-child(3){animation-delay:.12s;} .stat-card:nth-child(3)::before{background:linear-gradient(90deg,var(--orange),transparent);}
        .stat-card:nth-child(4){animation-delay:.16s;} .stat-card:nth-child(4)::before{background:linear-gradient(90deg,var(--purple),transparent);}
        .sc-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; border:1px solid transparent; }
        .si-admin  { background:var(--admin-dim);  color:var(--admin);  border-color:rgba(224,53,53,0.14); }
        .si-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .si-orange { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.14); }
        .si-purple { background:var(--purple-dim); color:var(--purple); border-color:rgba(160,100,255,0.14); }
        .sc-val { font-family:var(--font-head); font-size:1.5rem; font-weight:800; color:var(--text); line-height:1; letter-spacing:-0.01em; }
        .sc-lbl { font-size:0.72rem; color:var(--text-muted); margin-top:3px; }
        .sc-sub { font-size:0.68rem; color:var(--text-dim); margin-top:4px; }

        /* ── FILTER PANEL ── */
        .filter-panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:18px 20px; margin-bottom:20px;
            opacity:0; animation:fadeUp 0.45s ease 0.18s forwards;
            position:relative;
        }
        .filter-panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(224,53,53,0.18),transparent); }
        .filter-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:16px; }
        .filter-group { display:flex; flex-direction:column; gap:6px; }
        .filter-label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); }
        .filter-input, .filter-select {
            padding:10px 13px; background:var(--surface2); border:1px solid var(--border);
            border-radius:var(--radius-sm); color:var(--text-warm);
            font-family:var(--font-body); font-size:0.875rem; outline:none;
            transition:all 0.2s;
        }
        .filter-input:focus, .filter-select:focus { border-color:var(--admin); box-shadow:0 0 0 3px var(--admin-dim); background:var(--surface3); }
        .filter-input::placeholder { color:var(--text-dim); }
        .filter-select option { background:#201a13; }
        .filter-actions { display:flex; gap:10px; justify-content:flex-end; }

        /* ── BUTTONS ── */
        .btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.82rem; font-weight:600; text-decoration:none; cursor:pointer; border:none; transition:all 0.22s; white-space:nowrap; letter-spacing:0.01em; }
        .btn-admin  { background:linear-gradient(135deg,var(--admin),#b01e1e); color:white; box-shadow:0 3px 12px var(--admin-glow); }
        .btn-admin:hover  { transform:translateY(-1px); box-shadow:0 6px 18px var(--admin-glow); }
        .btn-ghost  { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }
        .btn-ghost:hover  { color:var(--text-warm); border-color:var(--border3); }
        .btn-danger { background:var(--red-dim);    color:var(--red);    border:1px solid rgba(255,77,109,0.2); font-size:0.75rem; padding:6px 11px; }
        .btn-danger:hover { background:var(--red); color:white; }
        .btn-teal   { background:var(--teal-dim);   color:var(--teal);   border:1px solid rgba(0,212,170,0.2);  font-size:0.75rem; padding:6px 11px; }
        .btn-teal:hover   { background:var(--teal); color:white; }
        .btn-blue   { background:var(--blue-dim);   color:var(--blue);   border:1px solid rgba(78,159,255,0.2); font-size:0.75rem; padding:6px 11px; }
        .btn-blue:hover   { background:var(--blue); color:white; }
        .btn-purple { background:var(--purple-dim); color:var(--purple); border:1px solid rgba(160,100,255,0.2);font-size:0.75rem; padding:6px 11px; }
        .btn-purple:hover { background:var(--purple); color:white; }

        /* ── TABLE PANEL ── */
        .table-panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden;
            opacity:0; animation:fadeUp 0.45s ease 0.24s forwards;
            position:relative;
        }
        .table-panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(224,53,53,0.15),transparent); z-index:1; }
        .tp-head { display:flex; align-items:center; justify-content:space-between; padding:15px 20px; border-bottom:1px solid var(--border); }
        .tp-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:8px; letter-spacing:-0.01em; }
        .pti { width:26px; height:26px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.72rem; }
        .pti-admin { background:var(--admin-dim); color:var(--admin); border:1px solid rgba(224,53,53,0.14); }
        .count-chip { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); font-size:0.68rem; font-weight:700; padding:3px 9px; border-radius:10px; }

        /* ── TABLE ── */
        .users-table { width:100%; border-collapse:collapse; }
        .users-table thead tr { border-bottom:1px solid var(--border); }
        .users-table th { padding:11px 16px; font-size:0.67rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); text-align:left; background:var(--surface2); }
        .users-table td { padding:14px 16px; border-bottom:1px solid var(--border); font-size:0.875rem; vertical-align:middle; }
        .users-table tr:last-child td { border-bottom:none; }
        .users-table tbody tr { transition:background 0.18s; }
        .users-table tbody tr:hover td { background:rgba(224,53,53,0.03); }

        /* user cell */
        .u-ava { width:36px; height:36px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.75rem; flex-shrink:0; border:1px solid transparent; }
        .u-name { font-weight:600; color:var(--text-warm); font-size:0.875rem; }
        .u-sub  { font-size:0.72rem; color:var(--text-dim); margin-top:1px; }
        .u-id   { font-size:0.65rem; color:var(--text-dim); margin-top:1px; }

        /* contact cell */
        .contact-row { display:flex; align-items:center; gap:5px; font-size:0.78rem; color:var(--text-muted); margin-bottom:3px; }
        .contact-row i { color:var(--text-dim); font-size:0.7rem; width:12px; }

        /* badges */
        .rbadge { font-size:0.62rem; font-weight:700; text-transform:uppercase; padding:3px 8px; border-radius:10px; display:inline-flex; align-items:center; gap:3px; border:1px solid transparent; }
        .rb-admin  { background:var(--admin-dim);  color:var(--admin);  border-color:rgba(224,53,53,0.14); }
        .rb-midman { background:var(--purple-dim); color:var(--purple); border-color:rgba(160,100,255,0.14); }
        .rb-seller { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .rb-buyer  { background:var(--blue-dim);   color:var(--blue);   border-color:rgba(78,159,255,0.14); }

        .sbadge { font-size:0.62rem; font-weight:700; text-transform:uppercase; padding:3px 8px; border-radius:20px; display:inline-flex; align-items:center; gap:3px; border:1px solid transparent; }
        .sb-active    { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .sb-suspended { background:var(--red-dim);    color:var(--red);    border-color:rgba(255,77,109,0.14); }
        .sb-admin-p   { background:var(--admin-dim);  color:var(--admin);  border-color:rgba(224,53,53,0.14); }

        .action-row { display:flex; gap:6px; flex-wrap:wrap; }
        .p-date { font-size:0.78rem; color:var(--text-dim); }

        /* ── EMPTY ── */
        .empty { text-align:center; padding:56px 24px; }
        .empty-icon { width:70px; height:70px; background:var(--surface2); border:1px solid var(--border); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.8rem; color:var(--text-dim); margin:0 auto 16px; }
        .empty h4 { font-family:var(--font-head); color:var(--text-warm); margin-bottom:6px; letter-spacing:-0.01em; }
        .empty p  { font-size:0.875rem; color:var(--text-muted); margin-bottom:16px; }

        /* ── PAGINATION ── */
        .pagination { display:flex; justify-content:center; gap:6px; flex-wrap:wrap; padding:20px; border-top:1px solid var(--border); }
        .pagination a, .pagination span {
            padding:7px 13px; border:1px solid var(--border2);
            text-decoration:none; color:var(--text-muted);
            border-radius:var(--radius-sm); font-size:0.82rem; font-weight:600;
            transition:all 0.2s; background:var(--surface2);
        }
        .pagination a:hover { background:var(--admin-dim); color:var(--admin); border-color:rgba(224,53,53,0.25); }
        .pagination .pg-active { background:var(--admin); color:white; border-color:var(--admin); }
        .pagination .pg-disabled { color:var(--text-dim); pointer-events:none; opacity:0.4; }
        .pg-info { text-align:center; padding-bottom:16px; font-size:0.78rem; color:var(--text-dim); }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:1200px) { .stat-strip{grid-template-columns:repeat(2,1fr);} .filter-grid{grid-template-columns:1fr 1fr;} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
        @media(max-width:640px)  { .filter-grid{grid-template-columns:1fr;} .stat-strip{grid-template-columns:1fr 1fr;} }
    </style>
</head>
<body>
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <!-- SIDEBAR — identical to admin dashboard -->
    <aside class="sidebar" id="sidebar">
           <a href="dashboard.php" class="sidebar-logo">
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
            <a href="users.php"         class="nav-link active"><span class="nav-icon"><i class="fas fa-users"></i></span> Users</a>
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
                <span class="page-title">Manage Users</span>
            </div>
            <div class="topbar-right">
                <div class="notif-btn"><i class="fas fa-bell" style="font-size:0.9rem;"></i></div>
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
                    <div class="sc-icon si-admin"><i class="fas fa-users"></i></div>
                    <div><div class="sc-val"><?php echo $stat_total; ?></div><div class="sc-lbl">Total Users</div><div class="sc-sub">All registered accounts</div></div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-teal"><i class="fas fa-circle-check"></i></div>
                    <div><div class="sc-val"><?php echo $stat_active; ?></div><div class="sc-lbl">Active Users</div><div class="sc-sub">Currently active</div></div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-orange"><i class="fas fa-pause-circle"></i></div>
                    <div><div class="sc-val"><?php echo $stat_susp; ?></div><div class="sc-lbl">Suspended</div><div class="sc-sub"><?php echo $stat_susp>0?'Need attention':'All clear'; ?></div></div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-purple"><i class="fas fa-handshake"></i></div>
                    <div><div class="sc-val"><?php echo $stat_midmen; ?></div><div class="sc-lbl">Verified Midmen</div><div class="sc-sub">Trusted intermediaries</div></div>
                </div>
            </div>

            <!-- FILTERS -->
            <div class="filter-panel">
                <form method="GET" action="">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label class="filter-label">Search Users</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                   class="filter-input" placeholder="Username, email, name…">
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Role</label>
                            <select name="role" class="filter-select">
                                <option value="">All Roles</option>
                                <option value="admin"  <?php echo $role_filter=='admin' ?'selected':''; ?>>Admin</option>
                                <option value="midman" <?php echo $role_filter=='midman'?'selected':''; ?>>Midman</option>
                                <option value="seller" <?php echo $role_filter=='seller'?'selected':''; ?>>Seller</option>
                                <option value="buyer"  <?php echo $role_filter=='buyer' ?'selected':''; ?>>Buyer</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Status</label>
                            <select name="status" class="filter-select">
                                <option value="">All Status</option>
                                <option value="active"    <?php echo $status_filter=='active'   ?'selected':''; ?>>Active</option>
                                <option value="suspended" <?php echo $status_filter=='suspended'?'selected':''; ?>>Suspended</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-admin"><i class="fas fa-search"></i> Filter Results</button>
                        <a href="users.php" class="btn btn-ghost"><i class="fas fa-rotate-left"></i> Clear</a>
                    </div>
                </form>
            </div>

            <!-- TABLE -->
            <div class="table-panel">
                <div class="tp-head">
                    <div class="tp-title"><div class="pti pti-admin"><i class="fas fa-users"></i></div> User Management</div>
                    <span class="count-chip"><?php echo $pagination['total_rows'] ?? 0; ?> Users</span>
                </div>

                <?php if(mysqli_num_rows($users) > 0): ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($u = mysqli_fetch_assoc($users)):
                        $rb = match($u['role']) { 'admin'=>'rb-admin','midman'=>'rb-midman','seller'=>'rb-seller',default=>'rb-buyer' };
                        $ri = match($u['role']) { 'admin'=>'fa-crown','midman'=>'fa-handshake','seller'=>'fa-store',default=>'fa-user' };
                        // avatar bg per role
                        $ab = match($u['role']) { 'admin'=>'background:var(--admin-dim);color:var(--admin);border-color:rgba(224,53,53,0.14);','midman'=>'background:var(--purple-dim);color:var(--purple);border-color:rgba(160,100,255,0.14);','seller'=>'background:var(--teal-dim);color:var(--teal);border-color:rgba(0,212,170,0.14);',default=>'background:var(--blue-dim);color:var(--blue);border-color:rgba(78,159,255,0.14);' };
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="u-ava" style="<?php echo $ab; ?>"><?php echo strtoupper(substr($u['username'],0,2)); ?></div>
                                <div>
                                    <div class="u-name"><?php echo htmlspecialchars($u['username']); ?></div>
                                    <div class="u-sub"><?php echo htmlspecialchars($u['full_name']??''); ?></div>
                                    <div class="u-id">#<?php echo $u['id']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="contact-row"><i class="fas fa-envelope"></i><?php echo htmlspecialchars($u['email']); ?></div>
                            <?php if(!empty($u['phone'])): ?>
                            <div class="contact-row"><i class="fas fa-phone"></i><?php echo htmlspecialchars($u['phone']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="rbadge <?php echo $rb; ?>">
                                <i class="fas <?php echo $ri; ?>" style="font-size:0.52rem;"></i>
                                <?php echo ucfirst($u['role']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if($u['is_active']): ?>
                                <span class="sbadge sb-active"><i class="fas fa-circle-check" style="font-size:0.55rem;"></i> Active</span>
                            <?php else: ?>
                                <span class="sbadge sb-suspended"><i class="fas fa-pause-circle" style="font-size:0.55rem;"></i> Suspended</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="p-date"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></span></td>
                        <td>
                            <?php if($u['role'] != 'admin'): ?>
                            <div class="action-row">
                                <?php if($u['is_active']): ?>
                                    <a href="?action=suspend&id=<?php echo $u['id']; ?>" class="btn btn-danger" onclick="return confirm('Suspend this user?')">
                                        <i class="fas fa-pause"></i> Suspend
                                    </a>
                                <?php else: ?>
                                    <a href="?action=activate&id=<?php echo $u['id']; ?>" class="btn btn-teal" onclick="return confirm('Activate this user?')">
                                        <i class="fas fa-play"></i> Activate
                                    </a>
                                <?php endif; ?>
                                <?php if($u['role'] != 'midman'): ?>
                                    <a href="?action=make_midman&id=<?php echo $u['id']; ?>" class="btn btn-purple" onclick="return confirm('Promote to Midman?')">
                                        <i class="fas fa-handshake"></i> Midman
                                    </a>
                                <?php endif; ?>
                                <a href="?action=delete&id=<?php echo $u['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this user? This cannot be undone!')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                            <?php else: ?>
                                <span class="sbadge sb-admin-p"><i class="fas fa-shield-halved" style="font-size:0.55rem;"></i> Protected</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>

                <!-- PAGINATION -->
                <?php if($total_pages > 1):
                    $base = "users.php?";
                    if($search)        $base .= "search=".urlencode($search)."&";
                    if($role_filter)   $base .= "role=".urlencode($role_filter)."&";
                    if($status_filter) $base .= "status=".urlencode($status_filter)."&";
                ?>
                <div class="pagination">
                    <?php if($current_page > 1): ?>
                        <a href="<?php echo $base; ?>page=<?php echo $current_page-1; ?>"><i class="fas fa-chevron-left"></i> Prev</a>
                    <?php else: ?>
                        <span class="pg-disabled"><i class="fas fa-chevron-left"></i> Prev</span>
                    <?php endif; ?>
                    <?php for($i=max(1,$current_page-2); $i<=min($total_pages,$current_page+2); $i++): ?>
                        <?php if($i==$current_page): ?>
                            <span class="pg-active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo $base; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if($current_page < $total_pages): ?>
                        <a href="<?php echo $base; ?>page=<?php echo $current_page+1; ?>">Next <i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                        <span class="pg-disabled">Next <i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
                <div class="pg-info">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?> · <?php echo $pagination['total_rows']; ?> total users</div>
                <?php endif; ?>

                <?php else: ?>
                <div class="empty">
                    <div class="empty-icon"><i class="fas fa-users"></i></div>
                    <h4>No Users Found</h4>
                    <p>No users match your current filters.</p>
                    <a href="users.php" class="btn btn-admin"><i class="fas fa-users"></i> View All Users</a>
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
</script>
</body>
</html>