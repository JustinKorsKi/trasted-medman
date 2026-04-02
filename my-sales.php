<?php
require_once 'includes/config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'seller') {
    header('Location: login.php'); exit();
}

$seller_id     = $_SESSION['user_id'];
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter   = isset($_GET['date'])   ? $_GET['date']   : '';

$query = "SELECT t.*, p.title, p.image_path, p.game_name, u.username as buyer_name
          FROM transactions t
          JOIN products p ON t.product_id=p.id
          JOIN users u ON t.buyer_id=u.id
          WHERE t.seller_id=$seller_id";
if($status_filter != 'all') $query .= " AND t.status='".mysqli_real_escape_string($conn,$status_filter)."'";
if($date_filter)             $query .= " AND DATE(t.created_at)='".mysqli_real_escape_string($conn,$date_filter)."'";
$query .= " ORDER BY t.created_at DESC";
$sales = mysqli_query($conn, $query);

$sum = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) as total_sales,
     COALESCE(SUM(CASE WHEN status='completed' THEN amount ELSE 0 END),0) as completed_revenue,
     COALESCE(SUM(CASE WHEN status='pending'   THEN amount ELSE 0 END),0) as pending_revenue,
     COALESCE(AVG(CASE WHEN status='completed' THEN amount ELSE NULL END),0) as avg_sale
     FROM transactions WHERE seller_id=$seller_id"));

$status_cfg = [
    'pending'     => ['label'=>'Pending',     'color'=>'var(--orange)', 'bg'=>'var(--orange-dim)'],
    'in_progress' => ['label'=>'In Progress', 'color'=>'var(--blue)',   'bg'=>'var(--blue-dim)'],
    'shipped'     => ['label'=>'Shipped',     'color'=>'var(--purple)', 'bg'=>'var(--purple-dim)'],
    'delivered'   => ['label'=>'Delivered',   'color'=>'var(--teal)',   'bg'=>'var(--teal-dim)'],
    'completed'   => ['label'=>'Completed',   'color'=>'var(--teal)',   'bg'=>'var(--teal-dim)'],
    'disputed'    => ['label'=>'Disputed',    'color'=>'var(--red)',    'bg'=>'var(--red-dim)'],
    'cancelled'   => ['label'=>'Cancelled',   'color'=>'var(--text-dim)','bg'=>'var(--surface2)'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Sales — Trusted Midman</title>
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
            --surface:    #0f0b07z;
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
        .logo-sub  { font-size:0.65rem; color:var(--gold); letter-spacing:0.12em; text-transform:uppercase; display:block; font-family:var(--font-body); font-weight:600; }
        .sidebar-nav { flex:1; padding:18px 10px; overflow-y:auto; position:relative; z-index:1; }
        .nav-label { font-size:0.65rem; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:var(--text-dim); padding:12px 12px 7px; }
        .nav-link { display:flex; align-items:center; gap:11px; padding:10px 13px; border-radius:var(--radius-sm); text-decoration:none; color:var(--text-muted); font-size:0.9rem; font-weight:500; margin-bottom:2px; transition:all 0.2s; position:relative; }
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

        @keyframes fadeUp { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);} }

        /* ── PAGE HEAD ── */
        .page-head { margin-bottom:22px; opacity:0; animation:fadeUp 0.4s ease forwards; }
        .page-head h1 { font-family:var(--font-head); font-size:1.8rem; font-weight:800; color:var(--text); display:flex; align-items:center; gap:12px; letter-spacing:-0.01em; }
        .ph-icon { width:40px; height:40px; background:var(--teal-dim); border:1px solid rgba(0,212,170,0.14); border-radius:11px; display:flex; align-items:center; justify-content:center; color:var(--teal); font-size:1rem; }
        .page-head p { font-size:0.84rem; color:var(--text-muted); margin-top:6px; }

        /* ── STAT STRIP ── */
        .stat-strip { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:22px; }
        .sstat {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:18px 20px;
            display:flex; align-items:center; gap:14px;
            opacity:0; animation:fadeUp 0.4s ease forwards;
            transition:border-color 0.25s, transform 0.25s;
            position:relative; overflow:hidden;
        }
        .sstat::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; opacity:0; transition:opacity 0.25s; }
        .sstat:hover { border-color:var(--border2); transform:translateY(-2px); }
        .sstat:hover::before { opacity:1; }
        .sstat:nth-child(1) { animation-delay:.05s; } .sstat:nth-child(1)::before { background:linear-gradient(90deg,var(--gold),transparent); }
        .sstat:nth-child(2) { animation-delay:.10s; } .sstat:nth-child(2)::before { background:linear-gradient(90deg,var(--teal),transparent); }
        .sstat:nth-child(3) { animation-delay:.15s; } .sstat:nth-child(3)::before { background:linear-gradient(90deg,var(--orange),transparent); }
        .sstat:nth-child(4) { animation-delay:.20s; } .sstat:nth-child(4)::before { background:linear-gradient(90deg,var(--blue),transparent); }

        .sstat-icon { width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:0.95rem; flex-shrink:0; border:1px solid transparent; }
        .si-gold   { background:var(--gold-dim);   color:var(--gold);   border-color:rgba(240,165,0,0.14); }
        .si-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .si-orange { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.14); }
        .si-blue   { background:var(--blue-dim);   color:var(--blue);   border-color:rgba(78,159,255,0.14); }

        .sstat-val { font-family:var(--font-head); font-size:1.4rem; font-weight:800; color:var(--text); line-height:1; letter-spacing:-0.01em; }
        .sstat-lbl { font-size:0.72rem; color:var(--text-muted); margin-top:3px; }

        /* ── FILTER PANEL ── */
        .filter-panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:16px 20px;
            margin-bottom:20px;
            opacity:0; animation:fadeUp 0.4s ease 0.22s forwards;
            position:relative;
        }
        .filter-panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.15),transparent); }
        .filter-form { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }

        .f-select, .f-date {
            padding:10px 13px; background:var(--surface2);
            border:1px solid var(--border); border-radius:var(--radius-sm);
            color:var(--text-warm); font-family:var(--font-body); font-size:0.875rem;
            transition:all 0.2s; cursor:pointer; outline:none;
        }
        .f-select option { background:#201a13; }
        .f-select:focus, .f-date:focus { border-color:var(--gold); box-shadow:0 0 0 3px rgba(240,165,0,0.1); background:var(--surface3); }
        .f-date::-webkit-calendar-picker-indicator { filter:invert(0.5) sepia(1) saturate(2) hue-rotate(10deg); }

        /* ── BUTTONS ── */
        .btn { display:inline-flex; align-items:center; gap:7px; padding:9px 16px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.875rem; font-weight:600; text-decoration:none; cursor:pointer; border:none; transition:all 0.22s ease; white-space:nowrap; letter-spacing:0.01em; }
        .btn-gold  { background:linear-gradient(135deg,var(--gold),#d48500); color:#0f0c08; font-weight:700; box-shadow:0 3px 14px var(--gold-glow); }
        .btn-gold:hover { background:linear-gradient(135deg,var(--gold-lt),var(--gold)); transform:translateY(-2px); }
        .btn-ghost { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }
        .btn-ghost:hover { color:var(--text-warm); border-color:var(--border3); }
        .btn-sm { padding:7px 13px; font-size:0.82rem; }

        /* ── TABLE PANEL ── */
        .table-panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden;
            opacity:0; animation:fadeUp 0.4s ease 0.3s forwards;
        }
        .table-panel::before { content:''; display:block; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.18),transparent); }

        .sales-table { width:100%; border-collapse:collapse; }
        .sales-table thead tr { border-bottom:1px solid var(--border); }
        .sales-table th { padding:12px 16px; font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); text-align:left; background:var(--surface2); }
        .sales-table th:last-child { text-align:right; }
        .sales-table td { padding:14px 16px; border-bottom:1px solid var(--border); font-size:0.875rem; vertical-align:middle; }
        .sales-table tr:last-child td { border-bottom:none; }
        .sales-table tbody tr { transition:background 0.18s; }
        .sales-table tbody tr:hover td { background:rgba(255,180,60,0.03); }

        /* ── CELLS ── */
        .p-info  { display:flex; align-items:center; gap:12px; }
        .p-thumb { width:46px; height:38px; border-radius:8px; background:var(--surface2); border:1px solid var(--border); overflow:hidden; display:flex; align-items:center; justify-content:center; color:var(--text-dim); font-size:1rem; flex-shrink:0; }
        .p-thumb img { width:100%; height:100%; object-fit:cover; }
        .p-name  { font-weight:600; color:var(--text-warm); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:180px; }
        .p-game  { font-size:0.68rem; font-weight:700; background:var(--gold-dim); color:var(--gold); padding:2px 7px; border-radius:10px; display:inline-block; margin-top:3px; border:1px solid rgba(240,165,0,0.14); }

        .buyer-ava { width:28px; height:28px; border-radius:50%; background:var(--blue-dim); color:var(--blue); border:1px solid rgba(78,159,255,0.15); font-family:var(--font-head); font-weight:700; font-size:0.65rem; display:inline-flex; align-items:center; justify-content:center; margin-right:6px; }

        .amt { font-family:var(--font-head); font-weight:800; font-size:0.95rem; color:var(--gold); letter-spacing:-0.01em; }
        .fee { font-size:0.75rem; color:var(--text-dim); }

        .sbadge { font-size:0.62rem; font-weight:700; text-transform:uppercase; padding:3px 9px; border-radius:20px; white-space:nowrap; letter-spacing:0.05em; }

        .p-date { font-size:0.78rem; color:var(--text-dim); }

        .view-btn { width:30px; height:30px; border-radius:7px; background:var(--gold-dim); color:var(--gold); border:1px solid rgba(240,165,0,0.15); display:inline-flex; align-items:center; justify-content:center; font-size:0.72rem; text-decoration:none; transition:all 0.2s; float:right; }
        .view-btn:hover { background:var(--gold); color:#0f0c08; }

        /* ── EMPTY ── */
        .empty { text-align:center; padding:56px 20px; }
        .empty-icon { width:68px; height:68px; background:var(--surface2); border:1px solid var(--border); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.7rem; color:var(--text-dim); margin:0 auto 14px; }
        .empty h3 { font-family:var(--font-head); font-size:1.1rem; color:var(--text-warm); margin-bottom:6px; letter-spacing:-0.01em; }
        .empty p  { font-size:0.84rem; color:var(--text-muted); margin-bottom:16px; }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:1100px) { .stat-strip{grid-template-columns:repeat(2,1fr);} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
        @media(max-width:640px)  { .stat-strip{grid-template-columns:1fr 1fr;} .sales-table th:nth-child(4),.sales-table td:nth-child(4){display:none;} }
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
            <a href="my-products.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-box-open"></i></span> My Products</a>
            <a href="add-product.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-plus-circle"></i></span> Add Product</a>
            <a href="my-transactions.php" class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span>Transactions</a>
            <a href="my-sales.php"         class="nav-link active"><span class="nav-icon"><i class="fas fa-chart-line"></i></span> Sales</a>
            <a href="seller-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
            <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <div class="nav-label" style="margin-top:10px;">Account</div>
             <a href="apply-midmans.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span>Apply as Midman</a>
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
                <span class="page-title">My Sales</span>
            </div>
            <!-- <a href="add-product.php" class="btn btn-gold btn-sm"><i class="fas fa-plus"></i> Add Product</a> -->
        </header>

        <div class="content">

            <!-- PAGE HEAD -->
            <div class="page-head">
                <h1><div class="ph-icon"><i class="fas fa-chart-line"></i></div> My Sales</h1>
                <p>Track your earnings and sales performance across all transactions.</p>
            </div>

            <!-- STAT STRIP -->
            <div class="stat-strip">
                <div class="sstat">
                    <div class="sstat-icon si-gold"><i class="fas fa-receipt"></i></div>
                    <div><div class="sstat-val"><?php echo $sum['total_sales']??0; ?></div><div class="sstat-lbl">Total Sales</div></div>
                </div>
                <div class="sstat">
                    <div class="sstat-icon si-teal"><i class="fas fa-circle-check"></i></div>
                    <div><div class="sstat-val">$<?php echo number_format($sum['completed_revenue']??0,2); ?></div><div class="sstat-lbl">Completed Revenue</div></div>
                </div>
                <div class="sstat">
                    <div class="sstat-icon si-orange"><i class="fas fa-hourglass-half"></i></div>
                    <div><div class="sstat-val">$<?php echo number_format($sum['pending_revenue']??0,2); ?></div><div class="sstat-lbl">Pending Revenue</div></div>
                </div>
                <div class="sstat">
                    <div class="sstat-icon si-blue"><i class="fas fa-coins"></i></div>
                    <div><div class="sstat-val">$<?php echo number_format($sum['avg_sale']??0,2); ?></div><div class="sstat-lbl">Avg. Sale Value</div></div>
                </div>
            </div>

            <!-- FILTER -->
            <div class="filter-panel">
                <form method="GET" class="filter-form">
                    <select name="status" class="f-select">
                        <?php
                        $statuses = ['all'=>'All Status','pending'=>'Pending','in_progress'=>'In Progress','shipped'=>'Shipped','delivered'=>'Delivered','completed'=>'Completed','disputed'=>'Disputed','cancelled'=>'Cancelled'];
                        foreach($statuses as $val=>$lbl): ?>
                        <option value="<?php echo $val; ?>" <?php echo $status_filter==$val?'selected':''; ?>><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>" class="f-date">
                    <button type="submit" class="btn btn-gold"><i class="fas fa-filter"></i> Apply</button>
                    <a href="my-sales.php" class="btn btn-ghost"><i class="fas fa-rotate-left"></i> Reset</a>
                </form>
            </div>

            <!-- TABLE -->
            <div class="table-panel">
                <?php if(mysqli_num_rows($sales) > 0): ?>
                <table class="sales-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Buyer</th>
                            <th>Amount</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th style="text-align:right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($s = mysqli_fetch_assoc($sales)):
                        $sc = $status_cfg[$s['status']] ?? ['label'=>ucfirst($s['status']),'color'=>'var(--text-muted)','bg'=>'var(--surface2)'];
                    ?>
                    <tr>
                        <td>
                            <div class="p-info">
                                <div class="p-thumb">
                                    <?php if($s['image_path']): ?><img src="<?php echo htmlspecialchars($s['image_path']); ?>" alt=""><?php else: ?><i class="fas fa-gamepad"></i><?php endif; ?>
                                </div>
                                <div>
                                    <div class="p-name"><?php echo htmlspecialchars($s['title']); ?></div>
                                    <?php if(!empty($s['game_name'])): ?>
                                        <span class="p-game"><?php echo htmlspecialchars($s['game_name']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="buyer-ava"><?php echo strtoupper(substr($s['buyer_name'],0,2)); ?></span>
                            <?php echo htmlspecialchars($s['buyer_name']); ?>
                        </td>
                        <td><div class="amt">$<?php echo number_format($s['amount'],2); ?></div></td>
                        <td><div class="fee">$<?php echo number_format($s['service_fee']??0,2); ?></div></td>
                        <td>
                            <span class="sbadge" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['color']; ?>;">
                                <?php echo $sc['label']; ?>
                            </span>
                        </td>
                        <td><span class="p-date"><?php echo date('M d, Y', strtotime($s['created_at'])); ?></span></td>
                        <td>
                            <a href="transaction-detail.php?id=<?php echo $s['id']; ?>" class="view-btn" title="View"><i class="fas fa-eye"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty">
                    <div class="empty-icon"><i class="fas fa-chart-line"></i></div>
                    <h3>No Sales Found</h3>
                    <p><?php echo $status_filter!='all'||$date_filter ? 'Try adjusting your filters.' : "You haven't made any sales yet. Start selling your gaming items!"; ?></p>
                    <a href="add-product.php" class="btn btn-gold"><i class="fas fa-plus-circle"></i> List New Item</a>
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