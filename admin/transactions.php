<?php
require_once '../includes/config.php';


if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php'); exit();
}

// ── Filters ──
$search        = isset($_GET['search'])    ? mysqli_real_escape_string($conn,$_GET['search'])    : '';
$status_filter = isset($_GET['status'])    ? mysqli_real_escape_string($conn,$_GET['status'])    : '';
$date_from     = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to       = isset($_GET['date_to'])   ? $_GET['date_to']   : '';

// ── Main query ──
$query = "SELECT t.*, p.title as product_title,
          b.username as buyer_name, s.username as seller_name, m.username as midman_name
          FROM transactions t
          JOIN products p ON t.product_id = p.id
          JOIN users b ON t.buyer_id = b.id
          JOIN users s ON t.seller_id = s.id
          LEFT JOIN users m ON t.midman_id = m.id
          WHERE 1=1";

if($search)        $query .= " AND (p.title LIKE '%$search%' OR b.username LIKE '%$search%' OR s.username LIKE '%$search%')";
if($status_filter) $query .= " AND t.status='$status_filter'";
if($date_from)     $query .= " AND DATE(t.created_at) >= '$date_from'";
if($date_to)       $query .= " AND DATE(t.created_at) <= '$date_to'";
$query .= " ORDER BY t.created_at DESC";

$transactions = mysqli_query($conn, $query);

// ── Stats ──
$total_volume = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(amount),0) total FROM transactions WHERE status='completed'"))['total'];
$total_fees   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(service_fee),0) total FROM transactions WHERE status='completed'"))['total'];
$total_count  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM transactions"))['c'];
$avg_fee      = $total_volume > 0 ? ($total_fees / $total_volume) * 100 : 0;

// ── Sidebar badge counts ──
$pending_kyc   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM verification_requests WHERE status='pending'"))['c'];
$pending_apps  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM midman_applications WHERE status='pending'"))['c'];
$open_disputes = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM disputes WHERE status='open'"))['c'];

$display_name = $_SESSION['full_name'] ?? $_SESSION['username'];

$status_cfg = [
    'pending'     => ['label'=>'Pending',     'color'=>'var(--orange)', 'bg'=>'var(--orange-dim)', 'icon'=>'fa-clock'],
    'in_progress' => ['label'=>'In Progress', 'color'=>'var(--blue)',   'bg'=>'var(--blue-dim)',   'icon'=>'fa-spinner'],
    'shipped'     => ['label'=>'Shipped',     'color'=>'var(--purple)', 'bg'=>'var(--purple-dim)', 'icon'=>'fa-truck'],
    'delivered'   => ['label'=>'Delivered',   'color'=>'var(--teal)',   'bg'=>'var(--teal-dim)',   'icon'=>'fa-box-open'],
    'completed'   => ['label'=>'Completed',   'color'=>'var(--teal)',   'bg'=>'var(--teal-dim)',   'icon'=>'fa-circle-check'],
    'disputed'    => ['label'=>'Disputed',    'color'=>'var(--red)',    'bg'=>'var(--red-dim)',    'icon'=>'fa-triangle-exclamation'],
    'cancelled'   => ['label'=>'Cancelled',   'color'=>'var(--text-dim)','bg'=>'var(--surface2)', 'icon'=>'fa-ban'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Transactions — Trusted Midman Admin</title>
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
        .stat-card:nth-child(4){animation-delay:.16s;} .stat-card:nth-child(4)::before{background:linear-gradient(90deg,var(--blue),transparent);}
        .sc-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; border:1px solid transparent; }
        .si-admin  { background:var(--admin-dim);  color:var(--admin);  border-color:rgba(224,53,53,0.14); }
        .si-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .si-orange { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.14); }
        .si-blue   { background:var(--blue-dim);   color:var(--blue);   border-color:rgba(78,159,255,0.14); }
        .sc-val { font-family:var(--font-head); font-size:1.5rem; font-weight:800; color:var(--text); line-height:1; letter-spacing:-0.01em; }
        .sc-lbl { font-size:0.72rem; color:var(--text-muted); margin-top:3px; }
        .sc-sub { font-size:0.68rem; color:var(--text-dim); margin-top:4px; display:flex; align-items:center; gap:4px; }
        .sc-sub.pos { color:var(--teal); }

        /* ── FILTER PANEL ── */
        .filter-panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:18px 20px; margin-bottom:20px;
            opacity:0; animation:fadeUp 0.45s ease 0.18s forwards;
            position:relative;
        }
        .filter-panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(224,53,53,0.18),transparent); }
        .filter-grid { display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:14px; margin-bottom:16px; }
        .filter-group { display:flex; flex-direction:column; gap:6px; }
        .filter-label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); }
        .filter-input, .filter-select {
            padding:10px 13px; background:var(--surface2); border:1px solid var(--border);
            border-radius:var(--radius-sm); color:var(--text-warm);
            font-family:var(--font-body); font-size:0.875rem; outline:none; transition:all 0.2s;
        }
        .filter-input:focus, .filter-select:focus { border-color:var(--admin); box-shadow:0 0 0 3px var(--admin-dim); background:var(--surface3); }
        .filter-input::placeholder { color:var(--text-dim); }
        .filter-input[type="date"] { color-scheme:dark; }
        .filter-select option { background:#201a13; }
        .filter-actions { display:flex; gap:10px; justify-content:flex-end; }

        /* ── BUTTONS ── */
        .btn { display:inline-flex; align-items:center; gap:6px; padding:9px 16px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.84rem; font-weight:600; text-decoration:none; cursor:pointer; border:none; transition:all 0.22s; letter-spacing:0.01em; }
        .btn-admin { background:linear-gradient(135deg,var(--admin),#b01e1e); color:white; box-shadow:0 3px 12px var(--admin-glow); }
        .btn-admin:hover { transform:translateY(-1px); box-shadow:0 6px 18px var(--admin-glow); }
        .btn-ghost { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }
        .btn-ghost:hover { color:var(--text-warm); border-color:var(--border3); }
        .view-btn { width:32px; height:32px; padding:0; border-radius:var(--radius-sm); background:var(--admin-dim); color:var(--admin); border:1px solid rgba(224,53,53,0.15); display:inline-flex; align-items:center; justify-content:center; font-size:0.78rem; text-decoration:none; transition:all 0.2s; float:right; }
        .view-btn:hover { background:var(--admin); color:white; }

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
        .pti { width:26px; height:26px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.72rem; border:1px solid transparent; }
        .pti-admin { background:var(--admin-dim); color:var(--admin); border-color:rgba(224,53,53,0.14); }
        .count-chip { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); font-size:0.68rem; font-weight:700; padding:3px 9px; border-radius:10px; }

        /* ── TABLE ── */
        .tx-table { width:100%; border-collapse:collapse; }
        .tx-table thead tr { border-bottom:1px solid var(--border); }
        .tx-table th { padding:11px 16px; font-size:0.67rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); text-align:left; background:var(--surface2); }
        .tx-table th:last-child { text-align:right; }
        .tx-table td { padding:13px 16px; border-bottom:1px solid var(--border); font-size:0.875rem; vertical-align:middle; }
        .tx-table tr:last-child td { border-bottom:none; }
        .tx-table tbody tr { transition:background 0.18s; }
        .tx-table tbody tr:hover td { background:rgba(224,53,53,0.03); }

        /* txn id */
        .txn-id  { font-family:var(--font-head); font-size:0.88rem; font-weight:700; color:var(--admin); letter-spacing:-0.01em; display:block; }
        .txn-raw { font-size:0.68rem; color:var(--text-dim); margin-top:1px; }

        /* product */
        .p-name { font-weight:600; color:var(--text-warm); font-size:0.875rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:180px; display:block; }

        /* flow */
        .flow { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
        .flow-ava { width:22px; height:22px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.55rem; flex-shrink:0; border:1px solid transparent; }
        .fa-buyer  { background:var(--blue-dim);   color:var(--blue);   border-color:rgba(78,159,255,0.15); }
        .fa-seller { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.15); }
        .fa-midman { background:var(--purple-dim); color:var(--purple); border-color:rgba(160,100,255,0.15); }
        .fa-none   { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.15); }
        .flow-name { font-size:0.78rem; color:var(--text-muted); }
        .flow-arr  { color:var(--text-dim); font-size:0.6rem; }
        .flow-label { font-size:0.7rem; color:var(--orange); font-weight:600; }

        /* amounts */
        .amount-val { font-family:var(--font-head); font-weight:800; font-size:0.95rem; color:var(--text-warm); letter-spacing:-0.01em; }
        .fee-val    { font-family:var(--font-head); font-weight:700; font-size:0.88rem; color:var(--teal); letter-spacing:-0.01em; }

        /* badges */
        .sbadge { font-size:0.62rem; font-weight:700; text-transform:uppercase; padding:3px 9px; border-radius:20px; display:inline-flex; align-items:center; gap:4px; border:1px solid transparent; letter-spacing:0.04em; }

        /* date */
        .p-date { font-size:0.78rem; color:var(--text-dim); }
        .p-time { font-size:0.7rem; color:var(--text-dim); margin-top:1px; }

        /* ── EMPTY ── */
        .empty { text-align:center; padding:56px 24px; }
        .empty-icon { width:70px; height:70px; background:var(--surface2); border:1px solid var(--border); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.8rem; color:var(--text-dim); margin:0 auto 16px; }
        .empty h4 { font-family:var(--font-head); color:var(--text-warm); margin-bottom:6px; letter-spacing:-0.01em; }
        .empty p  { font-size:0.875rem; color:var(--text-muted); margin-bottom:16px; }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:1200px) { .stat-strip{grid-template-columns:repeat(2,1fr);} .filter-grid{grid-template-columns:1fr 1fr;} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} .tx-table th:nth-child(3),.tx-table td:nth-child(3){display:none;} }
        @media(max-width:540px)  { .stat-strip{grid-template-columns:1fr 1fr;} .filter-grid{grid-template-columns:1fr;} }
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
            <a href="users.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-users"></i></span> Users</a>
            <a href="transactions.php"  class="nav-link active"><span class="nav-icon"><i class="fas fa-arrows-left-right"></i></span> Transactions</a>
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
                <span class="page-title">Manage Transactions</span>
            </div>
            <div class="topbar-right">
                <div class="notif-btn"><i class="fas fa-bell" style="font-size:0.9rem;"></i></div>
            </div>
        </header>

        <div class="content">

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <!-- STAT STRIP -->
            <div class="stat-strip">
                <div class="stat-card">
                    <div class="sc-icon si-admin"><i class="fas fa-arrows-left-right"></i></div>
                    <div>
                        <div class="sc-val"><?php echo $total_count; ?></div>
                        <div class="sc-lbl">Total Transactions</div>
                        <div class="sc-sub"><i class="fas fa-receipt"></i> All time</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-teal"><i class="fas fa-dollar-sign"></i></div>
                    <div>
                        <div class="sc-val">
                            <?php
                            if($total_volume >= 1000000)   echo '$'.number_format($total_volume/1000000,1).'M';
                            elseif($total_volume >= 1000)  echo '$'.number_format($total_volume/1000,1).'K';
                            else                           echo '$'.number_format($total_volume,2);
                            ?>
                        </div>
                        <div class="sc-lbl">Total Volume</div>
                        <div class="sc-sub pos"><i class="fas fa-circle-check"></i> Completed only</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-orange"><i class="fas fa-coins"></i></div>
                    <div>
                        <div class="sc-val">$<?php echo number_format($total_fees,2); ?></div>
                        <div class="sc-lbl">Fees Collected</div>
                        <div class="sc-sub"><i class="fas fa-piggy-bank"></i> Platform revenue</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-blue"><i class="fas fa-percentage"></i></div>
                    <div>
                        <div class="sc-val"><?php echo number_format($avg_fee,1); ?>%</div>
                        <div class="sc-lbl">Average Fee Rate</div>
                        <div class="sc-sub"><i class="fas fa-calculator"></i> Commission rate</div>
                    </div>
                </div>
            </div>

            <!-- FILTERS -->
            <div class="filter-panel">
                <form method="GET" action="">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label class="filter-label">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                   class="filter-input" placeholder="Product, buyer, seller…">
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">Status</label>
                            <select name="status" class="filter-select">
                                <option value="">All Status</option>
                                <?php foreach($status_cfg as $val => $sc): ?>
                                <option value="<?php echo $val; ?>" <?php echo $status_filter==$val?'selected':''; ?>>
                                    <?php echo $sc['label']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">From Date</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="filter-input">
                        </div>
                        <div class="filter-group">
                            <label class="filter-label">To Date</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="filter-input">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-admin"><i class="fas fa-search"></i> Filter Results</button>
                        <a href="transactions.php" class="btn btn-ghost"><i class="fas fa-rotate-left"></i> Clear</a>
                    </div>
                </form>
            </div>

            <!-- TABLE -->
            <div class="table-panel">
                <div class="tp-head">
                    <div class="tp-title"><div class="pti pti-admin"><i class="fas fa-list"></i></div> Transaction Management</div>
                    <span class="count-chip"><?php echo mysqli_num_rows($transactions); ?> Records</span>
                </div>

                <?php if(mysqli_num_rows($transactions) > 0):
                    mysqli_data_seek($transactions, 0);
                    $counter = 1;
                ?>
                <table class="tx-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>Parties</th>
                            <th>Amount</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($t = mysqli_fetch_assoc($transactions)):
                        $txn_id = 'TXN-'.str_pad($counter, 4,'0',STR_PAD_LEFT);
                        $sc = $status_cfg[$t['status']] ?? ['label'=>ucfirst($t['status']),'color'=>'var(--text-muted)','bg'=>'var(--surface2)','icon'=>'fa-circle'];
                    ?>
                    <tr>
                        <td>
                            <span class="txn-id"><?php echo $txn_id; ?></span>
                            <span class="txn-raw">#<?php echo $t['id']; ?></span>
                        </td>
                        <td>
                            <span class="p-name"><?php echo htmlspecialchars(substr($t['product_title'],0,26)).(strlen($t['product_title'])>26?'…':''); ?></span>
                        </td>
                        <td>
                            <div class="flow">
                                <div class="flow-ava fa-buyer"><?php echo strtoupper(substr($t['buyer_name'],0,2)); ?></div>
                                <span class="flow-name"><?php echo htmlspecialchars($t['buyer_name']); ?></span>
                                <i class="fas fa-arrow-right flow-arr"></i>
                                <div class="flow-ava fa-seller"><?php echo strtoupper(substr($t['seller_name'],0,2)); ?></div>
                                <span class="flow-name"><?php echo htmlspecialchars($t['seller_name']); ?></span>
                                <?php if($t['midman_name']): ?>
                                    <i class="fas fa-arrow-right flow-arr"></i>
                                    <div class="flow-ava fa-midman"><?php echo strtoupper(substr($t['midman_name'],0,2)); ?></div>
                                    <span class="flow-name"><?php echo htmlspecialchars($t['midman_name']); ?></span>
                                <?php else: ?>
                                    <i class="fas fa-arrow-right flow-arr"></i>
                                    <span class="flow-label"><i class="fas fa-triangle-exclamation" style="font-size:0.6rem;"></i> No Midman</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><div class="amount-val">$<?php echo number_format($t['amount'],2); ?></div></td>
                        <td><div class="fee-val">$<?php echo number_format($t['service_fee'],2); ?></div></td>
                        <td>
                            <span class="sbadge" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['color']; ?>;">
                                <i class="fas <?php echo $sc['icon']; ?>" style="font-size:0.55rem;"></i>
                                <?php echo $sc['label']; ?>
                            </span>
                        </td>
                        <td>
                            <div class="p-date"><?php echo date('M d, Y', strtotime($t['created_at'])); ?></div>
                            <div class="p-time"><?php echo date('g:i A', strtotime($t['created_at'])); ?></div>
                        </td>
                        <td>
                            <a href="../transaction-detail.php?id=<?php echo $t['id']; ?>" class="view-btn">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php $counter++; endwhile; ?>
                    </tbody>
                </table>

                <?php else: ?>
                <div class="empty">
                    <div class="empty-icon"><i class="fas fa-arrows-left-right"></i></div>
                    <h4>No Transactions Found</h4>
                    <p>No transactions match your current filters.</p>
                    <a href="transactions.php" class="btn btn-admin"><i class="fas fa-arrows-left-right"></i> View All Transactions</a>
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