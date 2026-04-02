<?php
require_once 'includes/config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller') {
    header('Location: login.php'); exit();
}

// Sidebar badge count
$pending_tx_count = 0;
if($_SESSION['role'] === 'seller') {
    $ptq = mysqli_query($conn, "SELECT COUNT(*) c FROM transactions WHERE seller_id={$_SESSION['user_id']} AND status='pending'");
    $pending_tx_count = mysqli_fetch_assoc($ptq)['c'];
} elseif($_SESSION['role'] === 'midman') {
    $ptq = mysqli_query($conn, "SELECT COUNT(*) c FROM transactions WHERE midman_id={$_SESSION['user_id']} AND status='pending'");
    $pending_tx_count = mysqli_fetch_assoc($ptq)['c'];
}


$seller_id = $_SESSION['user_id'];

if(isset($_GET['delete'])) {
    $pid = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM products WHERE id=$pid AND seller_id=$seller_id");
    $_SESSION['success'] = 'Product deleted successfully.';
    header('Location: my-products.php'); exit();
}

$products    = mysqli_query($conn, "SELECT * FROM products WHERE seller_id=$seller_id ORDER BY created_at DESC");
$total       = mysqli_num_rows($products);
$avail_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM products WHERE seller_id=$seller_id AND status='available'"))['c'];
$sold_count  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM products WHERE seller_id=$seller_id AND status='sold'"))['c'];

$rows = [];
mysqli_data_seek($products, 0);
while($r = mysqli_fetch_assoc($products)) $rows[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Products — Trusted Midman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/responsive.css">
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

        html { scroll-behavior: smooth; }
        body { font-family:var(--font-body); background:var(--bg); color:var(--text-warm); min-height:100vh; overflow-x:hidden; -webkit-font-smoothing:antialiased; }
        .layout { display:flex; min-height:100vh; }

        /* ── SIDEBAR ── */
        .sidebar {
            width:var(--sidebar-w); background:var(--surface);
            border-right:1px solid var(--border);
            display:flex; flex-direction:column;
            position:fixed; top:0; left:0; height:100vh;
            z-index:100; transition:transform 0.35s cubic-bezier(.77,0,.18,1);
        }
        .sidebar::before {
            content:''; position:absolute; bottom:-80px; left:-80px;
            width:300px; height:300px;
            background:radial-gradient(circle,rgba(200,100,0,0.08) 0%,transparent 65%);
            pointer-events:none;
        }
        .sidebar-logo {
            display:flex; align-items:center; gap:12px;
            padding:26px 22px; text-decoration:none;
            border-bottom:1px solid var(--border); position:relative; z-index:1;
        }
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
        .logo-sub  { font-size:0.65rem; color:var(--gold); letter-spacing:0.12em; text-transform:uppercase; display:block; font-family:var(--font-body); font-weight:600; }

        .sidebar-nav { flex:1; padding:18px 10px; overflow-y:auto; position:relative; z-index:1; }
        .nav-label   { font-size:0.65rem; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:var(--text-dim); padding:12px 12px 7px; }
        .nav-link    { display:flex; align-items:center; gap:11px; padding:10px 13px; border-radius:var(--radius-sm); text-decoration:none; color:var(--text-muted); font-size:0.9rem; font-weight:500; margin-bottom:2px; transition:all 0.2s; position:relative; }
        .nav-link:hover { color:var(--text-warm); background:var(--surface2); }
        .nav-link.active { color:var(--gold); background:var(--gold-dim); border:1px solid rgba(240,165,0,0.12); }
        .nav-link.active::before { content:''; position:absolute; left:0; top:20%; bottom:20%; width:3px; background:var(--gold); border-radius:0 3px 3px 0; }
        .nav-icon { width:20px; text-align:center; font-size:0.9rem; flex-shrink:0; }

        .sidebar-footer { padding:14px; border-top:1px solid var(--border); position:relative; z-index:1; }
        .user-pill { display:flex; align-items:center; gap:10px; padding:10px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); }
        .ava { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--gold),#c47d00); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.85rem; color:#0f0c08; flex-shrink:0; box-shadow:0 0 10px rgba(240,165,0,0.2); }
        .pill-name { font-size:0.875rem; font-weight:500; color:var(--text-warm); }
        .pill-role { font-size:0.68rem; color:var(--gold); text-transform:uppercase; letter-spacing:0.09em; }

        /* ── MAIN ── */
        .main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; }
        .topbar { position:sticky; top:0; z-index:50; background:rgba(15,12,8,0.88); backdrop-filter:blur(24px); border-bottom:1px solid var(--border); padding:0 32px; height:64px; display:flex; align-items:center; justify-content:space-between; }
        .topbar-left { display:flex; align-items:center; gap:16px; }
        .hamburger { display:none; background:none; border:none; color:var(--text-muted); font-size:1.1rem; cursor:pointer; padding:6px; border-radius:7px; transition:color 0.2s; }
        .hamburger:hover { color:var(--text-warm); }
        .page-title { font-family:var(--font-head); font-size:1.15rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .content { padding:28px 32px; flex:1; }
        .nav-badge { margin-left:auto; background:var(--red-dim); color:var(--red); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(255,77,109,0.15); }
        /* ── ANIMATIONS ── */
        @keyframes fadeUp { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }

        /* ── ALERT ── */
        .alert { display:flex; align-items:center; gap:10px; padding:13px 16px; border-radius:var(--radius-sm); font-size:0.875rem; margin-bottom:20px; }
        .alert-success { background:var(--teal-dim); border:1px solid rgba(0,212,170,0.22); color:var(--teal); }

        /* ── PAGE HEAD ── */
        .page-head { display:flex; align-items:flex-end; justify-content:space-between; flex-wrap:wrap; gap:14px; margin-bottom:22px; opacity:0; animation:fadeUp 0.4s ease forwards; }
        .ph-left h1 { font-family:var(--font-head); font-size:1.7rem; font-weight:800; color:var(--text); display:flex; align-items:center; gap:12px; line-height:1; letter-spacing:-0.01em; }
        .ph-icon { width:40px; height:40px; background:var(--gold-dim); border:1px solid rgba(240,165,0,0.14); border-radius:11px; display:flex; align-items:center; justify-content:center; color:var(--gold); font-size:1rem; }
        .ph-sub { font-size:0.82rem; color:var(--text-muted); margin-top:7px; }

        /* ── STAT STRIP ── */
        .stat-strip { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:22px; }
        .sstat {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:16px 18px;
            display:flex; align-items:center; gap:13px;
            opacity:0; transform:translateY(8px); animation:fadeUp 0.4s ease forwards;
            transition:border-color 0.25s, transform 0.25s;
            position:relative; overflow:hidden;
        }
        .sstat::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; opacity:0; transition:opacity 0.25s; }
        .sstat:hover { border-color:var(--border2); transform:translateY(-2px); }
        .sstat:hover::before { opacity:1; }
        .sstat:nth-child(1) { animation-delay:.05s; }
        .sstat:nth-child(1)::before { background:linear-gradient(90deg,var(--gold),transparent); }
        .sstat:nth-child(2) { animation-delay:.10s; }
        .sstat:nth-child(2)::before { background:linear-gradient(90deg,var(--teal),transparent); }
        .sstat:nth-child(3) { animation-delay:.15s; }
        .sstat:nth-child(3)::before { background:linear-gradient(90deg,var(--text-dim),transparent); }

        .sstat-icon { width:38px; height:38px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:0.9rem; flex-shrink:0; border:1px solid transparent; }
        .si-gold  { background:var(--gold-dim);   color:var(--gold);     border-color:rgba(240,165,0,0.14); }
        .si-teal  { background:var(--teal-dim);   color:var(--teal);     border-color:rgba(0,212,170,0.14); }
        .si-muted { background:var(--surface2);   color:var(--text-dim); border-color:var(--border); }

        .sstat-val { font-family:var(--font-head); font-size:1.4rem; font-weight:800; color:var(--text); line-height:1; letter-spacing:-0.01em; }
        .sstat-lbl { font-size:0.72rem; color:var(--text-muted); margin-top:3px; }

        /* ── FILTER ROW ── */
        .filter-row { display:flex; align-items:center; gap:10px; margin-bottom:18px; flex-wrap:wrap; }
        .search-wrap { position:relative; flex:1; min-width:180px; }
        .search-wrap i { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--text-dim); font-size:0.8rem; }
        .search-input { width:100%; padding:10px 14px 10px 36px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); color:var(--text-warm); font-family:var(--font-body); font-size:0.875rem; transition:border-color 0.2s; outline:none; }
        .search-input::placeholder { color:var(--text-dim); }
        .search-input:focus { border-color:var(--gold); box-shadow:0 0 0 3px rgba(240,165,0,0.1); background:var(--surface3); }
        .filter-btn { padding:9px 15px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.82rem; font-weight:600; border:1px solid var(--border); background:var(--surface2); color:var(--text-muted); cursor:pointer; transition:all 0.2s; }
        .filter-btn:hover, .filter-btn.active { color:var(--gold); border-color:rgba(240,165,0,0.25); background:var(--gold-dim); }

        /* ── BUTTONS ── */
        .btn { display:inline-flex; align-items:center; gap:7px; padding:9px 16px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.875rem; font-weight:600; text-decoration:none; cursor:pointer; border:none; transition:all 0.22s ease; white-space:nowrap; }
        .btn-gold { background:linear-gradient(135deg,var(--gold),#d48500); color:#0f0c08; font-weight:700; box-shadow:0 3px 14px var(--gold-glow); }
        .btn-gold:hover { background:linear-gradient(135deg,var(--gold-lt),var(--gold)); transform:translateY(-2px); }
        .btn-ghost { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }
        .btn-ghost:hover { color:var(--text-warm); border-color:var(--border3); }
        .btn-sm { padding:7px 13px; font-size:0.8rem; }

        /* ── TABLE PANEL ── */
        .table-panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; opacity:0; transform:translateY(10px); animation:fadeUp 0.45s ease 0.2s forwards; }

        /* glossy top line */
        .table-panel::before { content:''; display:block; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.2),transparent); }

        /* ── TABLE ── */
        .prod-table { width:100%; border-collapse:collapse; }
        .prod-table thead tr { border-bottom:1px solid var(--border); }
        .prod-table th { padding:12px 16px; font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); text-align:left; background:var(--surface2); }
        .prod-table th:last-child { text-align:right; }
        .prod-table td { padding:14px 16px; border-bottom:1px solid var(--border); font-size:0.875rem; vertical-align:middle; }
        .prod-table tr:last-child td { border-bottom:none; }
        .prod-table tbody tr { transition:background 0.18s; }
        .prod-table tbody tr:hover td { background:rgba(255,180,60,0.03); }

        /* ── CELLS ── */
        .p-thumb { width:50px; height:42px; border-radius:9px; background:var(--surface2); border:1px solid var(--border); overflow:hidden; display:flex; align-items:center; justify-content:center; color:var(--text-dim); font-size:1.1rem; flex-shrink:0; }
        .p-thumb img { width:100%; height:100%; object-fit:cover; }

        .p-name { font-weight:600; color:var(--text-warm); margin-bottom:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:220px; }
        .p-desc { font-size:0.72rem; color:var(--text-dim); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:220px; }

        .game-chip { font-size:0.68rem; font-weight:700; background:var(--gold-dim); color:var(--gold); padding:3px 9px; border-radius:10px; display:inline-block; border:1px solid rgba(240,165,0,0.15); }

        .p-price { font-family:var(--font-head); font-weight:800; color:var(--gold); font-size:0.95rem; letter-spacing:-0.01em; }

        .sbadge { font-size:0.62rem; font-weight:700; text-transform:uppercase; padding:3px 9px; border-radius:20px; white-space:nowrap; letter-spacing:0.05em; }
        .sb-available { background:var(--teal-dim); color:var(--teal); border:1px solid rgba(0,212,170,0.15); }
        .sb-sold      { background:var(--surface2); color:var(--text-dim); border:1px solid var(--border2); }

        .p-date { font-size:0.78rem; color:var(--text-dim); }

        /* ── ACTION BUTTONS ── */
        .act-row { display:flex; gap:6px; justify-content:flex-end; }
        .act-btn { width:30px; height:30px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.72rem; text-decoration:none; transition:all 0.2s; cursor:pointer; border:none; }
        .act-view { background:var(--gold-dim);  color:var(--gold); border:1px solid rgba(240,165,0,0.15); }
        .act-view:hover { background:var(--gold); color:#0f0c08; }
        .act-edit { background:var(--blue-dim);  color:var(--blue); border:1px solid rgba(78,159,255,0.15); }
        .act-edit:hover { background:var(--blue); color:white; }
        .act-del  { background:var(--red-dim);   color:var(--red);  border:1px solid rgba(255,77,109,0.15); }
        .act-del:hover  { background:var(--red);  color:white; }

        /* ── EMPTY ── */
        .empty-row td { text-align:center; padding:56px 20px !important; }
        .empty-icon  { width:64px; height:64px; background:var(--surface2); border:1px solid var(--border); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.6rem; color:var(--text-dim); margin:0 auto 14px; }
        .empty-title { font-family:var(--font-head); font-size:1.1rem; color:var(--text-warm); margin-bottom:6px; letter-spacing:-0.01em; }
        .empty-sub   { font-size:0.84rem; color:var(--text-muted); margin-bottom:16px; }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:1100px) { :root { --sidebar-w:220px; } }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
        @media(max-width:640px)  { .stat-strip{grid-template-columns:1fr 1fr;} .p-name,.p-desc{max-width:120px;} }
        @media(max-width:480px)  { .prod-table th:nth-child(3),.prod-table td:nth-child(3),.prod-table th:nth-child(6),.prod-table td:nth-child(6){display:none;} }
    </style>
</head>
<body>
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <!-- SIDEBAR -->
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
            <div class="nav-label">Seller</div>
            <a href="seller-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
            <a href="my-products.php"      class="nav-link active"><span class="nav-icon"><i class="fas fa-box-open"></i></span> My Products</a>
            <a href="add-product.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-plus-circle"></i></span> Add Product</a>
            <a href="my-transactions.php" class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span>Transactions</a>
          <a href="my-sales.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-chart-line"></i></span> Sales 
            <?php if($pending_tx_count > 0): ?><span class="nav-badge"><?php echo $pending_tx_count; ?></span><?php endif; ?></a>
            <a href="seller-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
            <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <div class="nav-label" style="margin-top:10px;">Account</div>
             <a href="apply-midman.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span>Apply as Midman</a>
            <a href="profile.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a>
            <a href="logout.php"  class="nav-link" style="color:var(--text-dim);margin-top:6px;"><span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="ava"><?php echo strtoupper(substr($_SESSION['username'],0,2)); ?></div>
                <div>
                    <div class="pill-name"><?php echo htmlspecialchars($_SESSION['full_name']??$_SESSION['username']); ?></div>
                    <div class="pill-role">Seller</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <span class="page-title">Products</span>
            </div>
        </header>

        <div class="content">

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-circle-check"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <!-- PAGE HEAD -->
            <div class="page-head">
                <div class="ph-left">
                    <h1>
                        <div class="ph-icon"><i class="fas fa-box-open"></i></div>
                        My Products
                    </h1>
                    <div class="ph-sub">Manage all your marketplace listings</div>
                </div>
                <a href="add-product.php" class="btn btn-gold"><i class="fas fa-plus"></i> Add New Product</a>
            </div>

            <!-- STAT STRIP -->
            <div class="stat-strip">
                <div class="sstat">
                    <div class="sstat-icon si-gold"><i class="fas fa-layer-group"></i></div>
                    <div>
                        <div class="sstat-val"><?php echo $total; ?></div>
                        <div class="sstat-lbl">Total Listings</div>
                    </div>
                </div>
                <div class="sstat">
                    <div class="sstat-icon si-teal"><i class="fas fa-circle-check"></i></div>
                    <div>
                        <div class="sstat-val"><?php echo $avail_count; ?></div>
                        <div class="sstat-lbl">Available</div>
                    </div>
                </div>
                <div class="sstat">
                    <div class="sstat-icon si-muted"><i class="fas fa-ban"></i></div>
                    <div>
                        <div class="sstat-val"><?php echo $sold_count; ?></div>
                        <div class="sstat-lbl">Sold</div>
                    </div>
                </div>
            </div>

            <!-- FILTER ROW -->
            <div class="filter-row">
                <div class="search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" class="search-input" id="searchInput" placeholder="Search products…">
                </div>
                <button class="filter-btn active" data-filter="">All</button>
                <button class="filter-btn" data-filter="available">Available</button>
                <button class="filter-btn" data-filter="sold">Sold</button>
            </div>

            <!-- TABLE -->
            <div class="table-panel">
                <table class="prod-table" id="prodTable">
                    <thead>
                        <tr>
                            <th style="width:58px;"></th>
                            <th>Product</th>
                            <th>Game</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Listed</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="prodBody">
                        <?php if(count($rows) > 0):
                            foreach($rows as $p): ?>
                        <tr data-status="<?php echo $p['status']; ?>" data-title="<?php echo strtolower(htmlspecialchars($p['title'])); ?>">
                            <td>
                                <div class="p-thumb">
                                    <?php if($p['image_path']): ?>
                                        <img src="<?php echo htmlspecialchars($p['image_path']); ?>" alt="">
                                    <?php else: ?>
                                        <i class="fas fa-gamepad"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="p-name"><?php echo htmlspecialchars($p['title']); ?></div>
                                <div class="p-desc"><?php echo htmlspecialchars(substr($p['description']??'',0,55)); ?>…</div>
                            </td>
                            <td>
                                <?php if(!empty($p['game_name'])): ?>
                                    <span class="game-chip"><?php echo htmlspecialchars($p['game_name']); ?></span>
                                <?php else: ?>
                                    <span style="color:var(--text-dim);font-size:0.78rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="p-price">$<?php echo number_format($p['price'],2); ?></span></td>
                            <td>
                                <span class="sbadge <?php echo $p['status']=='available'?'sb-available':'sb-sold'; ?>">
                                    <?php echo ucfirst($p['status']); ?>
                                </span>
                            </td>
                            <td><span class="p-date"><?php echo date('M d, Y', strtotime($p['created_at'])); ?></span></td>
                            <td>
                                <div class="act-row">
                                    <a href="product-detail.php?id=<?php echo $p['id']; ?>" class="act-btn act-view" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="edit-product.php?id=<?php echo $p['id']; ?>"   class="act-btn act-edit" title="Edit"><i class="fas fa-pen"></i></a>
                                    <a href="?delete=<?php echo $p['id']; ?>" class="act-btn act-del" title="Delete" onclick="return confirm('Delete this product?')"><i class="fas fa-trash"></i></a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr class="empty-row">
                            <td colspan="7">
                                <div class="empty-icon"><i class="fas fa-box-open"></i></div>
                                <div class="empty-title">No products yet</div>
                                <div class="empty-sub">Start by listing your first product.</div>
                                <a href="add-product.php" class="btn btn-gold"><i class="fas fa-plus"></i> Add Product</a>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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

    let currentFilter = '';

    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.dataset.filter;
            applyFilters();
        });
    });

    document.getElementById('searchInput').addEventListener('input', applyFilters);

    function applyFilters() {
        const q = document.getElementById('searchInput').value.toLowerCase();
        document.querySelectorAll('#prodBody tr[data-status]').forEach(row => {
            const matchStatus = !currentFilter || row.dataset.status === currentFilter;
            const matchSearch = !q || row.dataset.title.includes(q);
            row.style.display = matchStatus && matchSearch ? '' : 'none';
        });
    }
</script>
</body>
</html>