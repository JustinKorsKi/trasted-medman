<?php
require_once '../includes/config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php'); exit();
}

// ── Handle settings update — logic unchanged ──
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $service_fee = floatval($_POST['service_fee']);
    $site_name   = mysqli_real_escape_string($conn, $_POST['site_name']);
    $admin_email = mysqli_real_escape_string($conn, $_POST['admin_email']);
    $min_payout  = floatval($_POST['min_payout']);
    $_SESSION['success'] = 'Settings updated successfully!';
    header('Location: settings.php'); exit();
}

// ── Current settings (defaults) — logic unchanged ──
$service_fee = 5.00;
$site_name   = 'Trusted Midman';
$admin_email = 'admin@trustedmidman.com';
$min_payout  = 50.00;

// ── Sidebar badge counts ──
$pending_kyc   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM verification_requests WHERE status='pending'"))['c'];
$pending_apps  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM midman_applications WHERE status='pending'"))['c'];
$open_disputes = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM disputes WHERE status='open'"))['c'];

// ── System info queries — logic unchanged ──
$total_users   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users"))['c'];
$total_txns    = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM transactions"))['c'];
$total_midmen  = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users WHERE role='midman'"))['c'];
$total_disputes= mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM disputes WHERE status='open'"))['c'];

$display_name = $_SESSION['full_name'] ?? $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings — Trusted Midman Admin</title>
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

        /* ── SETTINGS GRID ── */
        .settings-grid { display:grid; grid-template-columns:1.6fr 1fr; gap:20px; align-items:start; }

        /* ── PANELS ── */
        .panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden;
            opacity:0; animation:fadeUp 0.45s ease forwards;
            position:relative;
        }
        .panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(224,53,53,0.18),transparent); z-index:1; }
        .panel:nth-child(1){animation-delay:.05s;} .panel:nth-child(2){animation-delay:.12s;}
        .panel-head { display:flex; align-items:center; gap:10px; padding:16px 22px; border-bottom:1px solid var(--border); }
        .ph-icon { width:28px; height:28px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.78rem; border:1px solid transparent; }
        .ph-admin  { background:var(--admin-dim);  color:var(--admin);  border-color:rgba(224,53,53,0.14); }
        .ph-blue   { background:var(--blue-dim);   color:var(--blue);   border-color:rgba(78,159,255,0.14); }
        .ph-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .ph-orange { background:var(--orange-dim); color:var(--orange); border-color:rgba(255,150,50,0.14); }
        .ph-title  { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .panel-body { padding:22px; }

        /* ── FORM SECTIONS ── */
        .form-section { margin-bottom:24px; padding-bottom:24px; border-bottom:1px solid var(--border); }
        .form-section:last-of-type { margin-bottom:0; padding-bottom:0; border-bottom:none; }
        .section-title { font-family:var(--font-head); font-size:0.92rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
        .section-icon { width:22px; height:22px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:0.65rem; border:1px solid transparent; }

        /* ── FORM ELEMENTS ── */
        .form-group { display:flex; flex-direction:column; gap:6px; margin-bottom:16px; }
        .form-group:last-child { margin-bottom:0; }
        .form-label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); }
        .form-help  { font-size:0.72rem; color:var(--text-dim); }
        .form-input {
            padding:11px 14px; background:var(--surface2); border:1px solid var(--border);
            border-radius:var(--radius-sm); color:var(--text-warm);
            font-family:var(--font-body); font-size:0.9rem; outline:none; transition:all 0.22s;
        }
        .form-input:focus { border-color:var(--admin); box-shadow:0 0 0 3px var(--admin-dim); background:var(--surface3); }
        .form-input::placeholder { color:var(--text-dim); }

        /* input with prefix/suffix decoration */
        .input-wrap { position:relative; display:flex; align-items:center; }
        .input-prefix { position:absolute; left:13px; font-size:0.9rem; color:var(--text-muted); pointer-events:none; }
        .input-suffix { position:absolute; right:13px; font-size:0.78rem; color:var(--text-dim); pointer-events:none; }
        .input-wrap .form-input { padding-left:26px; }
        .input-wrap .form-input.has-suffix { padding-right:36px; }

        /* ── BUTTONS ── */
        .btn { display:inline-flex; align-items:center; gap:7px; padding:10px 20px; border:none; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.875rem; font-weight:600; cursor:pointer; text-decoration:none; transition:all 0.22s; letter-spacing:0.01em; }
        .btn-save  { background:linear-gradient(135deg,var(--admin),#b01e1e); color:white; box-shadow:0 3px 12px var(--admin-glow); }
        .btn-save:hover  { transform:translateY(-1px); box-shadow:0 6px 18px var(--admin-glow); }
        .btn-reset { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }
        .btn-reset:hover { color:var(--text-warm); border-color:var(--border3); }
        .btn-action { width:100%; justify-content:center; background:var(--surface2); color:var(--text-muted); border:1px solid var(--border); padding:10px 14px; font-size:0.84rem; }
        .btn-action:hover { color:var(--text-warm); border-color:var(--border2); background:var(--surface3); }
        .btn-row { display:flex; gap:10px; margin-top:22px; }

        /* ── SYSTEM INFO TABLE ── */
        .sysinfo-table { width:100%; border-collapse:collapse; }
        .sysinfo-table tr { border-bottom:1px solid var(--border); transition:background 0.18s; }
        .sysinfo-table tr:last-child { border-bottom:none; }
        .sysinfo-table tr:hover td { background:rgba(224,53,53,0.03); }
        .sysinfo-table td { padding:12px 18px; font-size:0.875rem; vertical-align:middle; }
        .sysinfo-table td:first-child { color:var(--text-muted); font-weight:600; width:60%; display:flex; align-items:center; gap:9px; }
        .sysinfo-table td:first-child i { font-size:0.78rem; color:var(--text-dim); width:14px; text-align:center; }
        .sysinfo-table td:last-child { color:var(--text-warm); font-family:var(--font-head); font-size:0.92rem; font-weight:700; letter-spacing:-0.01em; }

        /* divider */
        .divider { height:1px; background:var(--border); margin:18px 0; }

        /* quick action section */
        .qa-title { font-family:var(--font-head); font-size:0.88rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; margin-bottom:12px; display:flex; align-items:center; gap:8px; }
        .qa-stack { display:flex; flex-direction:column; gap:8px; }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:900px)  { .settings-grid{grid-template-columns:1fr;} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
    </style>
</head>
<body>
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <!-- SIDEBAR — identical to admin dashboard, Settings active -->
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
            <a href="settings.php"      class="nav-link active"><span class="nav-icon"><i class="fas fa-gear"></i></span> Settings</a>
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
                <span class="page-title">System Settings</span>
            </div>
            <div class="topbar-right">
                <div class="notif-btn"><i class="fas fa-bell" style="font-size:0.9rem;"></i></div>
            </div>
        </header>

        <div class="content">

            <?php if(isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <div class="settings-grid">

                <!-- ── GENERAL SETTINGS FORM ── -->
                <div class="panel">
                    <div class="panel-head">
                        <div class="ph-icon ph-admin"><i class="fas fa-gear"></i></div>
                        <span class="ph-title">General Settings</span>
                    </div>
                    <div class="panel-body">
                        <form method="POST" action="">

                            <!-- Site Configuration -->
                            <div class="form-section">
                                <div class="section-title">
                                    <div class="section-icon ph-blue"><i class="fas fa-globe"></i></div>
                                    Site Configuration
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Site Name</label>
                                    <input type="text" name="site_name"
                                           value="<?php echo htmlspecialchars($site_name); ?>"
                                           class="form-input" required>
                                    <span class="form-help">The name displayed across your platform</span>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Admin Email</label>
                                    <input type="email" name="admin_email"
                                           value="<?php echo htmlspecialchars($admin_email); ?>"
                                           class="form-input" required>
                                    <span class="form-help">Contact email for administrative matters</span>
                                </div>
                            </div>

                            <!-- Fee Configuration -->
                            <div class="form-section">
                                <div class="section-title">
                                    <div class="section-icon ph-orange"><i class="fas fa-coins"></i></div>
                                    Fee Configuration
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Service Fee Percentage</label>
                                    <div class="input-wrap">
                                        <input type="number" name="service_fee"
                                               step="0.1" min="0" max="100"
                                               value="<?php echo $service_fee; ?>"
                                               class="form-input has-suffix" required>
                                        <span class="input-suffix">%</span>
                                    </div>
                                    <span class="form-help">Percentage charged on each transaction</span>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Minimum Payout Amount</label>
                                    <div class="input-wrap">
                                        <span class="input-prefix">$</span>
                                        <input type="number" name="min_payout"
                                               step="0.01" min="0"
                                               value="<?php echo $min_payout; ?>"
                                               class="form-input" required>
                                    </div>
                                    <span class="form-help">Minimum balance before midmen can withdraw</span>
                                </div>
                            </div>

                            <div class="btn-row">
                                <button type="submit" class="btn btn-save">
                                    <i class="fas fa-floppy-disk"></i> Save Settings
                                </button>
                                <button type="button" class="btn btn-reset" onclick="window.location.reload()">
                                    <i class="fas fa-rotate-left"></i> Reset
                                </button>
                            </div>

                        </form>
                    </div>
                </div>

                <!-- ── SYSTEM INFO + QUICK ACTIONS ── -->
                <div>

                    <!-- System Info -->
                    <div class="panel" style="margin-bottom:18px;">
                        <div class="panel-head">
                            <div class="ph-icon ph-teal"><i class="fas fa-server"></i></div>
                            <span class="ph-title">System Information</span>
                        </div>
                        <table class="sysinfo-table">
                            <tr>
                                <td><i class="fas fa-code"></i> PHP Version</td>
                                <td><?php echo phpversion(); ?></td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-database"></i> Database</td>
                                <td>MySQL</td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-server"></i> Server</td>
                                <td style="font-size:0.78rem;font-family:var(--font-body);font-weight:500;"><?php echo htmlspecialchars(substr($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown', 0, 22)); ?></td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-users"></i> Total Users</td>
                                <td><?php echo $total_users; ?></td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-arrows-left-right"></i> Transactions</td>
                                <td><?php echo $total_txns; ?></td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-handshake"></i> Verified Midmen</td>
                                <td><?php echo $total_midmen; ?></td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-gavel"></i> Open Disputes</td>
                                <td style="color:<?php echo $total_disputes>0?'var(--red)':'var(--teal)'; ?>">
                                    <?php echo $total_disputes; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><i class="fas fa-clock"></i> Last Updated</td>
                                <td style="font-size:0.78rem;font-family:var(--font-body);font-weight:500;color:var(--text-muted);"><?php echo date('M d, Y · g:i A'); ?></td>
                            </tr>
                        </table>
                    </div>

                    <!-- Quick Actions -->
                    <div class="panel">
                        <div class="panel-head">
                            <div class="ph-icon ph-admin"><i class="fas fa-bolt"></i></div>
                            <span class="ph-title">Quick Actions</span>
                        </div>
                        <div class="panel-body">
                            <div class="qa-stack">
                                <a href="charts.php" class="btn btn-action">
                                    <i class="fas fa-chart-bar"></i> View Analytics
                                </a>
                                <a href="users.php" class="btn btn-action">
                                    <i class="fas fa-users"></i> Manage Users
                                </a>
                                <a href="applications.php" class="btn btn-action">
                                    <i class="fas fa-user-check"></i> Review Applications
                                </a>
                            </div>
                        </div>
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
</script>
</body>
</html>