<?php
require_once 'includes/config.php';

if(!isset($_SESSION['user_id'])) {
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

// Default value (important to avoid errors)
$two_factor_enabled = false;

// Get 2FA status from DB
$result = mysqli_query($conn, "SELECT two_factor_enabled FROM users WHERE id = $user_id");

if($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $two_factor_enabled = (bool)$row['two_factor_enabled'];
}

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];

// Pending transaction count for sidebar badge
$pending_tx_count = 0;
if($role === 'seller') {
    $ptq = mysqli_query($conn, "SELECT COUNT(*) c FROM transactions WHERE seller_id=$user_id AND status='pending'");
    $pending_tx_count = mysqli_fetch_assoc($ptq)['c'];
} elseif($role === 'midman') {
    $ptq = mysqli_query($conn, "SELECT COUNT(*) c FROM transactions WHERE midman_id=$user_id AND status='pending'");
    $pending_tx_count = mysqli_fetch_assoc($ptq)['c'];
}

if($role == 'buyer') {
    $query = "SELECT t.*, p.title as product_title, p.image_path, u.username as seller_name
              FROM transactions t
              JOIN products p ON t.product_id = p.id
              JOIN users u ON t.seller_id = u.id
              WHERE t.buyer_id = $user_id
              ORDER BY t.created_at DESC";
} elseif($role == 'seller') {
    $query = "SELECT t.*, p.title as product_title, p.image_path, u.username as buyer_name
              FROM transactions t
              JOIN products p ON t.product_id = p.id
              JOIN users u ON t.buyer_id = u.id
              WHERE t.seller_id = $user_id
              ORDER BY t.created_at DESC";
} elseif($role == 'midman') {
    $query = "SELECT t.*, p.title as product_title, p.image_path,
              b.username as buyer_name, s.username as seller_name
              FROM transactions t
              JOIN products p ON t.product_id = p.id
              JOIN users b ON t.buyer_id = b.id
              JOIN users s ON t.seller_id = s.id
              WHERE t.midman_id = $user_id
              ORDER BY t.created_at DESC";
}

$result = mysqli_query($conn, $query);
$total  = mysqli_num_rows($result);

$stats = ['pending'=>0,'in_progress'=>0,'completed'=>0,'disputed'=>0,'cancelled'=>0,'total_spent'=>0];
$rows  = [];
while($r = mysqli_fetch_assoc($result)) {
    $rows[] = $r;
    $s = $r['status'];
    if(isset($stats[$s])) $stats[$s]++;
    if($r['status'] == 'completed') $stats['total_spent'] += $r['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Transactions — Trusted Midman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }

        :root {
            /* base dark colors */
            --bg:         #0f0c08;
            --bg2:        #130f0a;
            --surface:    #0f0b07;
            --surface2:   #201a13;
            --surface3:   #271f16;
            --border:     rgba(255,180,80,0.08);
            --border2:    rgba(255,180,80,0.15);
            --border3:    rgba(255,180,80,0.24);

            /* accent colors – will be overridden for midman */
            --accent:       #f0a500;
            --accent-lt:    #ffbe3a;
            --accent-dim:   rgba(240,165,0,0.13);
            --accent-glow:  rgba(240,165,0,0.28);
            --gradient-start: #f0a500;
            --gradient-end:   #d4920a;

            /* semantic colors */
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
            --purple-lt:  #be8fff;
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
            --accent-lt:    var(--purple-lt);
            --accent-dim:   var(--purple-dim);
            --accent-glow:  var(--purple-glow);
            --gradient-start: #a064ff;
            --gradient-end:   #7040cc;
        }

        html { scroll-behavior:smooth; }
        body { font-family:var(--font-body); background:var(--bg); color:var(--text-warm); min-height:100vh; overflow-x:hidden; -webkit-font-smoothing:antialiased; }
        .layout { display:flex; min-height:100vh; }

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
        .nav-badge { margin-left:auto; background:var(--red-dim); color:var(--red); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(255,77,109,0.15); }
        .security-badge { margin-left:auto; background:var(--teal-dim); color:var(--teal); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(0,212,170,0.15); }
        .sidebar-footer { padding:14px; border-top:1px solid var(--border); position:relative; z-index:1; }
        .user-pill { display:flex; align-items:center; gap:10px; padding:10px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); }
        .avatar { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.85rem; color:#0f0c08; flex-shrink:0; box-shadow:0 0 10px var(--accent-glow); }
        .user-pill-name { font-size:0.875rem; font-weight:500; color:var(--text-warm); }
        .user-pill-role { font-size:0.68rem; color:var(--accent); text-transform:uppercase; letter-spacing:0.09em; }

        /* ── MAIN ── */
        .main { margin-left:var(--sidebar-w); flex:1; min-height:100vh; display:flex; flex-direction:column; }
        .topbar { position:sticky; top:0; z-index:50; background:rgba(15,12,8,0.88); backdrop-filter:blur(24px); border-bottom:1px solid var(--border); padding:0 32px; height:64px; display:flex; align-items:center; justify-content:space-between; }
        .topbar-left { display:flex; align-items:center; gap:16px; }
        .hamburger { display:none; background:none; border:none; color:var(--text-muted); font-size:1.1rem; cursor:pointer; padding:6px; border-radius:7px; transition:color 0.2s; }
        .hamburger:hover { color:var(--text-warm); }
        .page-title { font-family:var(--font-head); font-size:1.15rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .online-dot { display:flex; align-items:center; gap:7px; font-size:0.78rem; color:var(--text-muted); }
        .online-dot::before { content:''; width:7px; height:7px; border-radius:50%; background:var(--accent); box-shadow:0 0 8px var(--accent-glow); }
        .content { padding:28px 32px; flex:1; }

        @keyframes fadeUp  { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);} }
        @keyframes slideIn { from{opacity:0;transform:translateX(-6px);}to{opacity:1;transform:translateX(0);} }

        /* ── PAGE HEAD ── */
        .page-head { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:14px; }
        .page-head h1 { font-family:var(--font-head); font-size:1.8rem; font-weight:800; color:var(--text); display:flex; align-items:center; gap:12px; line-height:1; letter-spacing:-0.01em; }
        .page-head-icon { width:42px; height:42px; background:var(--accent-dim); border:1px solid rgba(240,165,0,0.14); border-radius:12px; display:flex; align-items:center; justify-content:center; color:var(--accent); font-size:1.1rem; }
        .page-head-sub  { font-size:0.84rem; color:var(--text-muted); margin-top:6px; }
        .count-chip { background:var(--accent-dim); color:var(--accent); font-size:0.72rem; font-weight:700; padding:3px 10px; border-radius:20px; letter-spacing:0.04em; border:1px solid rgba(240,165,0,0.15); }

        /* ── FILTER ── */
        .filter-row { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
        .filter-select { padding:10px 13px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); color:var(--text-warm); font-family:var(--font-body); font-size:0.875rem; cursor:pointer; transition:all 0.2s; min-width:150px; outline:none; }
        .filter-select:focus { border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-dim); background:var(--surface3); }
        .filter-select option { background:#201a13; }

        /* ── STAT STRIP ── */
        .stat-strip { display:grid; grid-template-columns:repeat(5,1fr); gap:12px; margin-bottom:24px; }
        .stat-chip {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:14px 16px;
            display:flex; align-items:center; gap:12px;
            transition:border-color 0.22s, transform 0.22s;
            cursor:pointer;
            opacity:0; transform:translateY(10px);
            animation:fadeUp 0.4s ease forwards;
            position:relative;
        }
        .stat-chip::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; opacity:0; transition:opacity 0.22s; border-radius:var(--radius) var(--radius) 0 0; }
        .stat-chip:nth-child(1)::before { background:var(--text-muted); }
        .stat-chip:nth-child(2)::before { background:var(--orange); }
        .stat-chip:nth-child(3)::before { background:var(--blue); }
        .stat-chip:nth-child(4)::before { background:var(--teal); }
        .stat-chip:nth-child(5)::before { background:var(--red); }
        .stat-chip:nth-child(1){animation-delay:.04s} .stat-chip:nth-child(2){animation-delay:.09s} .stat-chip:nth-child(3){animation-delay:.14s} .stat-chip:nth-child(4){animation-delay:.19s} .stat-chip:nth-child(5){animation-delay:.24s}
        .stat-chip:hover { border-color:var(--border2); transform:translateY(-2px); }
        .stat-chip:hover::before { opacity:1; }
        .stat-chip.active-filter { border-color:var(--accent); background:var(--accent-dim); }
        .stat-chip.active-filter::before { opacity:1; background:var(--accent) !important; }

        .chip-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
        .dot-all       { background:var(--text-muted); }
        .dot-pending   { background:var(--orange); box-shadow:0 0 6px rgba(255,150,50,0.5); }
        .dot-progress  { background:var(--blue);   box-shadow:0 0 6px rgba(78,159,255,0.5); }
        .dot-completed { background:var(--teal);   box-shadow:0 0 6px rgba(0,212,170,0.5); }
        .dot-disputed  { background:var(--red);    box-shadow:0 0 6px rgba(255,77,109,0.5); }

        .chip-val { font-family:var(--font-head); font-size:1.2rem; font-weight:800; color:var(--text); line-height:1; letter-spacing:-0.01em; }
        .chip-lbl { font-size:0.72rem; color:var(--text-muted); margin-top:2px; }

        /* ── ALERTS ── */
        .alert { display:flex; align-items:center; gap:10px; padding:13px 18px; border-radius:var(--radius-sm); font-size:0.875rem; margin-bottom:18px; }
        .alert-success { background:var(--teal-dim); color:var(--teal); border:1px solid rgba(0,212,170,0.2); }
        .alert-error   { background:var(--red-dim);  color:#ff7090;     border:1px solid rgba(255,77,109,0.2); }

        /* ── TRANSACTION LIST ── */
        .tx-list { display:flex; flex-direction:column; gap:12px; }

        .tx-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius);
            display:grid; grid-template-columns:88px 1fr auto;
            gap:20px; align-items:center;
            padding:18px 22px;
            transition:all 0.25s ease;
            opacity:0; transform:translateX(-6px);
            animation:slideIn 0.4s ease forwards;
            position:relative; overflow:hidden;
        }
        /* left color bar */
        .tx-card::before { content:''; position:absolute; left:0; top:0; bottom:0; width:3px; border-radius:3px 0 0 3px; background:var(--border2); transition:background 0.25s; }
        .tx-card[data-status="pending"]::before     { background:var(--orange); }
        .tx-card[data-status="in_progress"]::before { background:var(--blue); }
        .tx-card[data-status="completed"]::before   { background:var(--teal); }
        .tx-card[data-status="disputed"]::before    { background:var(--red); }

        .tx-card:nth-child(1){animation-delay:.04s} .tx-card:nth-child(2){animation-delay:.09s} .tx-card:nth-child(3){animation-delay:.14s} .tx-card:nth-child(4){animation-delay:.19s} .tx-card:nth-child(n+5){animation-delay:.24s}

        .tx-card:hover { border-color:var(--border2); transform:translateX(4px); box-shadow:0 8px 28px rgba(0,0,0,0.35); }

        .tx-thumb { width:88px; height:66px; border-radius:var(--radius-sm); background:var(--surface2); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; color:var(--text-dim); font-size:1.5rem; overflow:hidden; flex-shrink:0; }
        .tx-thumb img { width:100%; height:100%; object-fit:cover; }

        .tx-details { flex:1; min-width:0; }
        .tx-title { font-family:var(--font-head); font-size:1rem; font-weight:700; color:var(--text); margin-bottom:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; letter-spacing:-0.01em; }
        .tx-meta  { display:flex; flex-wrap:wrap; gap:14px; margin-bottom:8px; }
        .tx-meta-item { display:flex; align-items:center; gap:6px; font-size:0.82rem; color:var(--text-muted); }
        .tx-meta-item i { font-size:0.75rem; color:var(--accent); }
        .tx-amount { font-family:var(--font-head); font-size:1.25rem; font-weight:800; color:var(--accent); display:inline-block; margin-right:10px; letter-spacing:-0.01em; }
        .tx-date   { font-size:0.76rem; color:var(--text-dim); margin-top:5px; display:flex; align-items:center; gap:5px; }
        .tx-date i { font-size:0.7rem; }

        .tx-right { display:flex; flex-direction:column; align-items:flex-end; gap:10px; flex-shrink:0; }

        .status-badge { font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; padding:4px 10px; border-radius:20px; white-space:nowrap; }
        .status-pending     { background:var(--orange-dim); color:var(--orange); border:1px solid rgba(255,150,50,0.15); }
        .status-in-progress { background:var(--blue-dim);   color:var(--blue);   border:1px solid rgba(78,159,255,0.15); }
        .status-completed   { background:var(--teal-dim);   color:var(--teal);   border:1px solid rgba(0,212,170,0.15); }
        .status-disputed    { background:var(--red-dim);    color:var(--red);    border:1px solid rgba(255,77,109,0.15); }
        .status-cancelled   { background:var(--surface2);   color:var(--text-dim); border:1px solid var(--border2); }

        .tx-actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }

        .btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.8rem; font-weight:600; text-decoration:none; cursor:pointer; border:none; transition:all 0.22s ease; white-space:nowrap; letter-spacing:0.01em; }
        .btn-primary { background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); color:#0f0c08; font-weight:700; box-shadow:0 3px 12px var(--accent-glow); }
        .btn-primary:hover { background:linear-gradient(135deg,var(--accent-lt),var(--accent)); transform:translateY(-1px); }
        .btn-ghost  { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }
        .btn-ghost:hover  { color:var(--text-warm); border-color:var(--border3); }
        .btn-danger { background:var(--red-dim);    color:var(--red);    border:1px solid rgba(255,77,109,0.2); }
        .btn-danger:hover { background:rgba(255,77,109,0.22); }
        .btn-warn   { background:var(--orange-dim); color:var(--orange); border:1px solid rgba(255,150,50,0.2); }
        .btn-warn:hover   { background:rgba(255,150,50,0.22); }

        /* ── EMPTY ── */
        .empty { text-align:center; padding:64px 24px; color:var(--text-muted); }
        .empty-icon { width:80px; height:80px; background:var(--surface2); border:1px solid var(--border); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:2rem; color:var(--text-dim); margin:0 auto 20px; }
        .empty h3 { font-family:var(--font-head); font-size:1.2rem; color:var(--text-warm); margin-bottom:8px; letter-spacing:-0.01em; }
        .empty p  { font-size:0.875rem; margin-bottom:22px; }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:1100px) { :root{--sidebar-w:220px;} }
        @media(max-width:900px)  { .stat-strip{grid-template-columns:repeat(3,1fr);} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} .tx-card{grid-template-columns:1fr;} .tx-thumb{width:100%;height:120px;} .tx-right{align-items:flex-start;flex-direction:row;flex-wrap:wrap;} }
        @media(max-width:540px)  { .stat-strip{grid-template-columns:repeat(2,1fr);} }
    </style>
</head>
<body class="role-<?php echo $role; ?>">
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <!-- SIDEBAR — dynamic per role -->
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
            <?php if($role == 'seller'): ?>
                <div class="nav-label">Seller</div>
                <a href="seller-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="my-products.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-box-open"></i></span> My Products</a>
                <a href="add-product.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-plus-circle"></i></span> Add Product</a>
                <a href="my-transactions.php" class="nav-link active"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> Transactions</a>
                <a href="my-sales.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-chart-line"></i></span> Sales 
                    <?php if($pending_tx_count > 0): ?><span class="nav-badge"><?php echo $pending_tx_count; ?></span><?php endif; ?></a>
                <a href="seller-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <?php elseif($role == 'buyer'): ?>
                <div class="nav-label">Buyer</div>
                <a href="buyer-dashboard.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="products.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-store"></i></span> Browse Products</a>
                <a href="my-transactions.php"  class="nav-link active"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> My Purchases</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span>Dispute Center</a>
            <?php elseif($role == 'midman'): ?>
                <div class="nav-label">Midman</div>
                <a href="midman-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
               <a href="my-transactions.php" class="nav-link active"><span class="nav-icon"><i class="fas fa-handshake"></i></span> Transactions
                    <?php if($pending_tx_count > 0): ?><span class="nav-badge"><?php echo $pending_tx_count; ?></span><?php endif; ?></a>
                <a href="midman-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span>Dispute Center
                    <?php if($stats['disputed']>0): ?><span class="nav-badge"><?php echo $stats['disputed']; ?></span><?php endif; ?></a>
                <a href="verify-identity.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span> KYC Status</a>
                
            <?php endif; ?>

            <?php if($role == 'midman'): ?>
                <div class="nav-label" style="margin-top:10px;">Security</div>
                <a href="setup-2fa.php" class="nav-link">
                    <span class="nav-icon"><i class="fas fa-shield-alt"></i></span>
                    <?php echo $two_factor_enabled ? 'Manage 2FA' : 'Enable 2FA'; ?>
                    <?php if($two_factor_enabled): ?><span class="security-badge">Active</span><?php endif; ?>
                </a>
            <?php endif; ?>

            <div class="nav-label" style="margin-top:10px;">Account</div>
            <?php if($role !== 'midman'): ?><a href="apply-midman.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span>Apply as Midman</a><?php endif; ?>
            <a href="profile.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a>
            <a href="logout.php"  class="nav-link" style="color:var(--text-dim);margin-top:6px;"><span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['username'], 0, 2)); ?></div>
                <div>
                    <div class="user-pill-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></div>
                    <div class="user-pill-role"><?php echo ucfirst($role); ?></div>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <span class="page-title">My Transactions</span>
            </div>
            <div class="online-dot">Online</div>
        </header>

        <div class="content">
            <!-- PAGE HEAD -->
            <div class="page-head">
                <div>
                    <h1>
                        <div class="page-head-icon"><i class="fas fa-receipt"></i></div>
                        Transactions
                        <span class="count-chip"><?php echo $total; ?> total</span>
                    </h1>
                    <div class="page-head-sub">
                        <?php if($role=='buyer'): ?>Your full purchase history
                        <?php elseif($role=='seller'): ?>Orders placed for your listings
                        <?php else: ?>Transactions assigned to you as midman
                        <?php endif; ?>
                    </div>
                </div>
                <div class="filter-row">
                    <select class="filter-select" id="sortFilter">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="highest">Highest Amount</option>
                        <option value="lowest">Lowest Amount</option>
                    </select>
                </div>
            </div>

            <!-- STAT STRIP -->
            <div class="stat-strip">
                <div class="stat-chip active-filter" data-filter="" onclick="filterByChip(this,'')">
                    <div class="chip-dot dot-all"></div>
                    <div><div class="chip-val"><?php echo $total; ?></div><div class="chip-lbl">All</div></div>
                </div>
                <div class="stat-chip" data-filter="pending" onclick="filterByChip(this,'pending')">
                    <div class="chip-dot dot-pending"></div>
                    <div><div class="chip-val"><?php echo $stats['pending']; ?></div><div class="chip-lbl">Pending</div></div>
                </div>
                <div class="stat-chip" data-filter="in_progress" onclick="filterByChip(this,'in_progress')">
                    <div class="chip-dot dot-progress"></div>
                    <div><div class="chip-val"><?php echo $stats['in_progress']; ?></div><div class="chip-lbl">In Progress</div></div>
                </div>
                <div class="stat-chip" data-filter="completed" onclick="filterByChip(this,'completed')">
                    <div class="chip-dot dot-completed"></div>
                    <div><div class="chip-val"><?php echo $stats['completed']; ?></div><div class="chip-lbl">Completed</div></div>
                </div>
                <div class="stat-chip" data-filter="disputed" onclick="filterByChip(this,'disputed')">
                    <div class="chip-dot dot-disputed"></div>
                    <div><div class="chip-val"><?php echo $stats['disputed']; ?></div><div class="chip-lbl">Disputed</div></div>
                </div>
            </div>

            <!-- ALERTS -->
            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <!-- TRANSACTIONS -->
            <?php if(count($rows) > 0): ?>
            <div class="tx-list" id="txList">
                <?php foreach($rows as $t): ?>
                <div class="tx-card"
                     data-status="<?php echo $t['status']; ?>"
                     data-amount="<?php echo $t['amount']; ?>"
                     data-date="<?php echo $t['created_at']; ?>">

                    <div class="tx-thumb">
                        <?php if($t['image_path']): ?>
                            <img src="<?php echo htmlspecialchars($t['image_path']); ?>" alt="">
                        <?php else: ?>
                            <i class="fas fa-gamepad"></i>
                        <?php endif; ?>
                    </div>

                    <div class="tx-details">
                        <div class="tx-title"><?php echo htmlspecialchars($t['product_title']); ?></div>
                        <div class="tx-meta">
                            <div class="tx-meta-item">
                                <i class="fas fa-user"></i>
                                <?php if($role=='buyer'):   echo 'Seller: '.htmlspecialchars($t['seller_name']);
                                elseif($role=='seller'):    echo 'Buyer: '.htmlspecialchars($t['buyer_name']);
                                elseif($role=='midman'):    echo 'Buyer: '.htmlspecialchars($t['buyer_name']); endif; ?>
                            </div>
                            <?php if($role=='midman'): ?>
                            <div class="tx-meta-item">
                                <i class="fas fa-store"></i>
                                Seller: <?php echo htmlspecialchars($t['seller_name']); ?>
                            </div>
                            <?php endif; ?>
                            <div class="tx-meta-item">
                                <i class="fas fa-receipt"></i>
                                Fee: $<?php echo number_format($t['service_fee'], 2); ?>
                            </div>
                        </div>
                        <div>
                            <span class="tx-amount">$<?php echo number_format($t['amount'], 2); ?></span>
                        </div>
                        <div class="tx-date">
                            <i class="fas fa-calendar-days"></i>
                            <?php echo date('M d, Y · g:i A', strtotime($t['created_at'])); ?>
                        </div>
                    </div>

                    <div class="tx-right">
                        <span class="status-badge status-<?php echo str_replace('_','-',$t['status']); ?>">
                            <?php echo ucfirst(str_replace('_',' ',$t['status'])); ?>
                        </span>
                        <div class="tx-actions">
                            <a href="transaction-detail.php?id=<?php echo $t['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <?php if($role=='buyer' && $t['status']=='completed'): ?>
                                <a href="rate-transactions.php?id=<?php echo $t['id']; ?>" class="btn btn-ghost">
                                    <i class="fas fa-star"></i> Rate
                                </a>
                            <?php endif; ?>
                            <?php if(in_array($role,['buyer','seller']) && !in_array($t['status'],['completed','cancelled','disputed'])): ?>
                                <a href="raise-dispute.php?transaction_id=<?php echo $t['id']; ?>" class="btn btn-warn">
                                    <i class="fas fa-triangle-exclamation"></i> Dispute
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty">
                <div class="empty-icon"><i class="fas fa-bag-shopping"></i></div>
                <h3>No Transactions Yet</h3>
                <p>
                    <?php if($role=='buyer'):       echo "You haven't made any purchases yet.";
                    elseif($role=='seller'):         echo "No orders have been placed for your products.";
                    elseif($role=='midman'):         echo "You haven't been assigned any transactions yet."; endif; ?>
                </p>
                <?php if($role=='buyer'): ?>
                    <a href="products.php"    class="btn btn-primary"><i class="fas fa-store"></i> Browse Products</a>
                <?php elseif($role=='seller'): ?>
                    <a href="add-product.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Product</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<script>
    const hamburger = document.getElementById('hamburger');
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('overlay');
    hamburger.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('visible'); });
    overlay.addEventListener('click',   () => { sidebar.classList.remove('open'); overlay.classList.remove('visible'); });

    let currentFilter = '';
    function filterByChip(el, status) {
        currentFilter = status;
        document.querySelectorAll('.stat-chip').forEach(c => c.classList.remove('active-filter'));
        el.classList.add('active-filter');
        applyFilterSort();
    }

    const sortFilter = document.getElementById('sortFilter');
    sortFilter?.addEventListener('change', applyFilterSort);

    function applyFilterSort() {
        const list  = document.getElementById('txList');
        if(!list) return;
        const cards = Array.from(list.querySelectorAll('.tx-card'));
        const sort  = sortFilter.value;
        cards.forEach(c => c.style.display = (!currentFilter || c.dataset.status === currentFilter) ? 'grid' : 'none');
        const visible = cards.filter(c => c.style.display !== 'none');
        visible.sort((a, b) => {
            if(sort === 'oldest')  return new Date(a.dataset.date)  - new Date(b.dataset.date);
            if(sort === 'highest') return parseFloat(b.dataset.amount) - parseFloat(a.dataset.amount);
            if(sort === 'lowest')  return parseFloat(a.dataset.amount) - parseFloat(b.dataset.amount);
            return new Date(b.dataset.date) - new Date(a.dataset.date);
        });
        visible.forEach(c => list.appendChild(c));
    }
</script>
</body>
</html>