<?php
require_once '../includes/config.php';


if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php'); exit();
}

// ── Monthly data (last 7 months) ──
$monthly_data = [];
for($i = 6; $i >= 0; $i--) {
    $month      = date('Y-m', strtotime("-$i months"));
    $month_name = date('M Y', strtotime("-$i months"));
    $trans = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) AS count, COALESCE(SUM(amount),0) AS total
         FROM transactions WHERE DATE_FORMAT(created_at,'%Y-%m')='$month'"));
    $monthly_data[] = [
        'month'  => $month_name,
        'count'  => (int)($trans['count'] ?? 0),
        'volume' => (float)($trans['total'] ?? 0),
    ];
}

// ── User distribution ──
$user_growth = [];
foreach(['buyer','seller','midman'] as $type)
    $user_growth[$type] = (int)mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users WHERE role='$type'"))['c'];

// ── Quick stats ──
$total_volume  = array_sum(array_column($monthly_data,'volume'));
$total_txns    = array_sum(array_column($monthly_data,'count'));
$total_users   = array_sum($user_growth);
$avg_txns      = round($total_txns / 7);

// ── Pending counts for badges ──
$pending_kyc  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM verification_requests WHERE status='pending'"))['c'];
$pending_apps = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM midman_applications WHERE status='pending'"))['c'];
$open_disputes= mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM disputes WHERE status='open'"))['c'];

$display_name = $_SESSION['full_name'] ?? $_SESSION['username'];

// ── Recent activity ──
$activity = mysqli_query($conn,
    "SELECT 'transaction' AS type, id, created_at FROM transactions
     UNION ALL
     SELECT 'user' AS type, id, created_at FROM users
     ORDER BY created_at DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics — Trusted Midman Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

            /* admin red accent */
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
}      .logo-text { font-family:var(--font-head); font-weight:700; font-size:1.1rem; color:var(--text); line-height:1.2; letter-spacing:-0.01em; }
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

        /* ── STAT STRIP ── */
        .stat-strip { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:24px; }
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
        .stat-card:nth-child(1) { animation-delay:.04s; } .stat-card:nth-child(1)::before { background:linear-gradient(90deg,var(--admin),transparent); }
        .stat-card:nth-child(2) { animation-delay:.08s; } .stat-card:nth-child(2)::before { background:linear-gradient(90deg,var(--teal),transparent); }
        .stat-card:nth-child(3) { animation-delay:.12s; } .stat-card:nth-child(3)::before { background:linear-gradient(90deg,var(--blue),transparent); }
        .stat-card:nth-child(4) { animation-delay:.16s; } .stat-card:nth-child(4)::before { background:linear-gradient(90deg,var(--orange),transparent); }
        .sc-icon { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1rem; flex-shrink:0; border:1px solid transparent; }
        .si-admin  { background:var(--admin-dim);  color:var(--admin);  border-color:rgba(224,53,53,0.14); }
        .si-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .si-blue   { background:var(--blue-dim);   color:var(--blue);   border-color:rgba(78,159,255,0.14); }
        .si-orange { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.14); }
        .sc-val { font-family:var(--font-head); font-size:1.5rem; font-weight:800; color:var(--text); line-height:1; letter-spacing:-0.01em; }
        .sc-lbl { font-size:0.72rem; color:var(--text-muted); margin-top:3px; }
        .sc-sub { font-size:0.68rem; color:var(--text-dim); margin-top:4px; display:flex; align-items:center; gap:4px; }
        .sc-sub.pos { color:var(--teal); }

        /* ── CHARTS GRID ── */
        .charts-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }

        .chart-panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden;
            opacity:0; animation:fadeUp 0.45s ease forwards;
            position:relative;
        }
        .chart-panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(224,53,53,0.18),transparent); z-index:1; }
        .chart-panel:nth-child(1){animation-delay:.2s;} .chart-panel:nth-child(2){animation-delay:.26s;}

        .chart-head { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--border); }
        .chart-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:9px; letter-spacing:-0.01em; }
        .cti { width:26px; height:26px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.72rem; border:1px solid transparent; }
        .cti-admin  { background:var(--admin-dim);  color:var(--admin);  border-color:rgba(224,53,53,0.14); }
        .cti-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .cti-blue   { background:var(--blue-dim);   color:var(--blue);   border-color:rgba(78,159,255,0.14); }
        .cti-orange { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.14); }
        .chart-sub { font-size:0.72rem; color:var(--text-dim); }
        .chart-body { padding:20px; }

        /* canvas containers — need explicit height for Chart.js responsive */
        .canvas-wrap { position:relative; height:260px; }

        /* ── ACTIVITY TABLE ── */
        .activity-wrap { max-height:260px; overflow-y:auto; }
        .act-table { width:100%; border-collapse:collapse; }
        .act-table thead tr { border-bottom:1px solid var(--border); }
        .act-table th { padding:10px 16px; font-size:0.67rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); text-align:left; background:var(--surface2); }
        .act-table td { padding:11px 16px; border-bottom:1px solid var(--border); font-size:0.84rem; vertical-align:middle; }
        .act-table tr:last-child td { border-bottom:none; }
        .act-table tbody tr:hover td { background:rgba(224,53,53,0.03); }
        .act-type { display:inline-flex; align-items:center; gap:6px; font-weight:600; font-size:0.8rem; padding:3px 9px; border-radius:20px; }
        .act-txn  { background:var(--teal-dim);  color:var(--teal);  border:1px solid rgba(0,212,170,0.14); }
        .act-user { background:var(--blue-dim);  color:var(--blue);  border:1px solid rgba(78,159,255,0.14); }
        .act-id   { font-family:var(--font-head); font-weight:700; font-size:0.85rem; color:var(--admin); letter-spacing:-0.01em; }
        .act-date { font-size:0.75rem; color:var(--text-dim); }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:1200px) { .stat-strip{grid-template-columns:repeat(2,1fr);} }
        @media(max-width:900px)  { .charts-grid{grid-template-columns:1fr;} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
        @media(max-width:540px)  { .stat-strip{grid-template-columns:1fr 1fr;} }
    </style>
</head>
<body>
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <!-- SIDEBAR -->
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
            <a href="charts.php"        class="nav-link active"><span class="nav-icon"><i class="fas fa-chart-bar"></i></span> Reports</a>

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
                <span class="page-title">Reports &amp; Analytics</span>
            </div>
            <div class="topbar-right">
                <div class="notif-btn"><i class="fas fa-bell" style="font-size:0.9rem;"></i></div>
            </div>
        </header>

        <div class="content">

            <!-- STAT STRIP -->
            <div class="stat-strip">
                <div class="stat-card">
                    <div class="sc-icon si-admin"><i class="fas fa-chart-line"></i></div>
                    <div>
                        <div class="sc-val">
                            <?php
                            if($total_volume >= 1000000)     echo '$'.number_format($total_volume/1000000,1).'M';
                            elseif($total_volume >= 1000)    echo '$'.number_format($total_volume/1000,1).'K';
                            else                             echo '$'.number_format($total_volume,0);
                            ?>
                        </div>
                        <div class="sc-lbl">7-Month Volume</div>
                        <div class="sc-sub pos"><i class="fas fa-arrow-trend-up"></i> Platform growth</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-teal"><i class="fas fa-arrows-left-right"></i></div>
                    <div>
                        <div class="sc-val"><?php echo $total_txns; ?></div>
                        <div class="sc-lbl">7-Month Transactions</div>
                        <div class="sc-sub"><i class="fas fa-chart-bar"></i> Total activity</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-blue"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="sc-val"><?php echo $total_users; ?></div>
                        <div class="sc-lbl">Total Users</div>
                        <div class="sc-sub"><i class="fas fa-user-plus"></i> Community size</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="sc-icon si-orange"><i class="fas fa-calculator"></i></div>
                    <div>
                        <div class="sc-val"><?php echo $avg_txns; ?></div>
                        <div class="sc-lbl">Avg Monthly Transactions</div>
                        <div class="sc-sub"><i class="fas fa-clock"></i> Monthly average</div>
                    </div>
                </div>
            </div>

            <!-- ROW 1: Volume + User Distribution -->
            <div class="charts-grid">
                <div class="chart-panel">
                    <div class="chart-head">
                        <div class="chart-title"><div class="cti cti-admin"><i class="fas fa-chart-line"></i></div> Transaction Volume</div>
                        <span class="chart-sub">Last 7 months</span>
                    </div>
                    <div class="chart-body">
                        <div class="canvas-wrap"><canvas id="volumeChart"></canvas></div>
                    </div>
                </div>
                <div class="chart-panel">
                    <div class="chart-head">
                        <div class="chart-title"><div class="cti cti-blue"><i class="fas fa-chart-pie"></i></div> User Distribution</div>
                        <span class="chart-sub">Current breakdown</span>
                    </div>
                    <div class="chart-body">
                        <div class="canvas-wrap"><canvas id="userChart"></canvas></div>
                    </div>
                </div>
            </div>

            <!-- ROW 2: Count + Activity -->
            <div class="charts-grid">
                <div class="chart-panel">
                    <div class="chart-head">
                        <div class="chart-title"><div class="cti cti-teal"><i class="fas fa-chart-bar"></i></div> Transaction Count</div>
                        <span class="chart-sub">Monthly volume</span>
                    </div>
                    <div class="chart-body">
                        <div class="canvas-wrap"><canvas id="countChart"></canvas></div>
                    </div>
                </div>
                <div class="chart-panel">
                    <div class="chart-head">
                        <div class="chart-title"><div class="cti cti-orange"><i class="fas fa-clock-rotate-left"></i></div> Recent Activity</div>
                        <span class="chart-sub">Latest platform events</span>
                    </div>
                    <div class="activity-wrap">
                        <table class="act-table">
                            <thead><tr><th>Type</th><th>ID</th><th>Date</th></tr></thead>
                            <tbody>
                            <?php while($row = mysqli_fetch_assoc($activity)): ?>
                            <tr>
                                <td>
                                    <?php if($row['type']=='transaction'): ?>
                                        <span class="act-type act-txn"><i class="fas fa-arrows-left-right" style="font-size:0.65rem;"></i> Transaction</span>
                                    <?php else: ?>
                                        <span class="act-type act-user"><i class="fas fa-user-plus" style="font-size:0.65rem;"></i> New User</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="act-id">#<?php echo $row['id']; ?></span></td>
                                <td><span class="act-date"><?php echo date('M d, H:i', strtotime($row['created_at'])); ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
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

    // ── shared theme ──
    const GRID_COLOR   = 'rgba(255,180,80,0.07)';
    const TICK_COLOR   = '#5a4e3a';
    const LEGEND_COLOR = '#a89880';

    Chart.defaults.color = TICK_COLOR;
    Chart.defaults.font.family = "'DM Sans', sans-serif";
    Chart.defaults.font.size   = 11;

    const monthlyData = <?php echo json_encode($monthly_data); ?>;
    const userGrowth  = <?php echo json_encode($user_growth); ?>;

    // ── Gradient helper — creates top-to-bottom canvas gradient ──
    function makeGradient(ctx, colorTop, colorBottom) {
        const chart = ctx.chart;
        const { top, bottom } = chart.chartArea || { top: 0, bottom: 260 };
        const grad = ctx.chart.ctx.createLinearGradient(0, top, 0, bottom);
        grad.addColorStop(0,   colorTop);
        grad.addColorStop(0.6, colorBottom.replace('0)', '0.18)'));
        grad.addColorStop(1,   colorBottom);
        return grad;
    }

    // ── Volume Chart — red gradient fade like the reference image ──
    const volumeCtx = document.getElementById('volumeChart');
    new Chart(volumeCtx, {
        type: 'line',
        data: {
            labels: monthlyData.map(m => m.month),
            datasets: [{
                label: 'Volume ($)',
                data: monthlyData.map(m => m.volume),
                borderColor: '#e03535',
                backgroundColor: function(context) {
                    const chart = context.chart;
                    const { ctx, chartArea } = chart;
                    if (!chartArea) return 'rgba(224,53,53,0.0)';
                    const grad = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                    grad.addColorStop(0,    'rgba(224,53,53,0.55)');
                    grad.addColorStop(0.45, 'rgba(224,53,53,0.22)');
                    grad.addColorStop(1,    'rgba(224,53,53,0.0)');
                    return grad;
                },
                borderWidth: 2.5,
                tension: 0.45,
                fill: true,
                pointBackgroundColor: '#e03535',
                pointBorderColor: '#1a1510',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 7,
                pointHoverBackgroundColor: '#ff5a5a',
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1a1510',
                    borderColor: 'rgba(224,53,53,0.4)',
                    borderWidth: 1,
                    titleColor: '#f0e8da',
                    bodyColor: '#a89880',
                    padding: 10,
                    callbacks: { label: ctx => '  $' + ctx.parsed.y.toLocaleString() }
                }
            },
            scales: {
                x: {
                    grid:{ color:GRID_COLOR, drawBorder:false },
                    ticks:{ color:TICK_COLOR }
                },
                y: {
                    grid:{ color:GRID_COLOR, drawBorder:false },
                    ticks:{ color:TICK_COLOR, callback: v => '$'+v.toLocaleString() },
                    beginAtZero: true
                }
            }
        }
    });

    // ── Count Chart — teal gradient fade ──
    const countCtx = document.getElementById('countChart');
    new Chart(countCtx, {
        type: 'bar',
        data: {
            labels: monthlyData.map(m => m.month),
            datasets: [{
                label: 'Transactions',
                data: monthlyData.map(m => m.count),
                backgroundColor: function(context) {
                    const chart = context.chart;
                    const { ctx, chartArea } = chart;
                    if (!chartArea) return 'rgba(0,212,170,0.7)';
                    const grad = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                    grad.addColorStop(0,   'rgba(0,212,170,0.85)');
                    grad.addColorStop(1,   'rgba(0,212,170,0.2)');
                    return grad;
                },
                hoverBackgroundColor: '#00d4aa',
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1a1510',
                    borderColor: 'rgba(0,212,170,0.4)',
                    borderWidth: 1,
                    titleColor: '#f0e8da',
                    bodyColor: '#a89880',
                    padding: 10,
                }
            },
            scales: {
                x: {
                    grid:{ color:GRID_COLOR, drawBorder:false },
                    ticks:{ color:TICK_COLOR }
                },
                y: {
                    grid:{ color:GRID_COLOR, drawBorder:false },
                    ticks:{ color:TICK_COLOR },
                    beginAtZero: true
                }
            }
        }
    });

    // ── User Doughnut ──
    new Chart(document.getElementById('userChart'), {
        type: 'doughnut',
        data: {
            labels: ['Buyers', 'Sellers', 'Midmen'],
            datasets: [{
                data: [userGrowth.buyer, userGrowth.seller, userGrowth.midman],
                backgroundColor: ['rgba(78,159,255,0.85)', 'rgba(0,212,170,0.85)', 'rgba(160,100,255,0.85)'],
                hoverBackgroundColor: ['#4e9fff', '#00d4aa', '#a064ff'],
                borderWidth: 0,
                hoverOffset: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: LEGEND_COLOR,
                        padding: 16,
                        usePointStyle: true,
                        pointStyleWidth: 8,
                    }
                },
                tooltip: {
                    backgroundColor: '#1a1510',
                    borderColor: 'rgba(255,180,80,0.15)',
                    borderWidth: 1,
                    titleColor: '#f0e8da',
                    bodyColor: '#a89880',
                }
            }
        }
    });
</script>
</body>
</html>