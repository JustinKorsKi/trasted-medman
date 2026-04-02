<?php
require_once 'includes/config.php';

$pending_tx_count = 0;
if($_SESSION['role'] === 'seller') {
    $ptq = mysqli_query($conn, "SELECT COUNT(*) c FROM transactions WHERE seller_id={$_SESSION['user_id']} AND status='pending'");
    $pending_tx_count = mysqli_fetch_assoc($ptq)['c'];
} elseif($_SESSION['role'] === 'midman') {
    $ptq = mysqli_query($conn, "SELECT COUNT(*) c FROM transactions WHERE midman_id={$_SESSION['user_id']} AND status='pending'");
    $pending_tx_count = mysqli_fetch_assoc($ptq)['c'];
}

if(!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? 'buyer';
$success = '';
$error   = '';

$check_query = mysqli_query($conn, "SELECT role FROM users WHERE id = $user_id");
$user = mysqli_fetch_assoc($check_query);

if($user['role'] == 'midman') { header('Location: midman-dashboard.php'); exit(); }

$app_check    = mysqli_query($conn, "SELECT * FROM midman_applications WHERE user_id = $user_id ORDER BY created_at DESC");
$existing_app = mysqli_fetch_assoc($app_check);

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $reason     = mysqli_real_escape_string($conn, $_POST['reason']);
    $experience = mysqli_real_escape_string($conn, $_POST['experience']);
    if(empty($reason) || empty($experience)) {
        $error = 'Please fill in all required fields.';
    } else {
        $q = "INSERT INTO midman_applications (user_id, reason, experience, status) VALUES ($user_id, '$reason', '$experience', 'pending')";
        if(mysqli_query($conn, $q)) {
            $success      = 'Application submitted! An admin will review it shortly.';
            $app_check    = mysqli_query($conn, "SELECT * FROM midman_applications WHERE user_id = $user_id ORDER BY created_at DESC");
            $existing_app = mysqli_fetch_assoc($app_check);
        } else {
            $error = 'Failed to submit application. Please try again.';
        }
    }
}

$display_name = $_SESSION['full_name'] ?? $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply as Midman — Trusted Midman</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/responsive.css">
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
            --purple:     #a064ff;
            --purple-dim: rgba(160,100,255,0.12);
            --blue:       #4e9fff;
            --blue-dim:   rgba(78,159,255,0.12);
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
         .nav-badge { margin-left:auto; background:var(--red-dim); color:var(--red); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(255,77,109,0.15); }


        /* ── MAIN ── */
        .main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; }
        .topbar { position:sticky; top:0; z-index:50; background:rgba(15,12,8,0.88); backdrop-filter:blur(24px); border-bottom:1px solid var(--border); padding:0 32px; height:64px; display:flex; align-items:center; justify-content:space-between; }
        .topbar-left { display:flex; align-items:center; gap:16px; }
        .hamburger { display:none; background:none; border:none; color:var(--text-muted); font-size:1.1rem; cursor:pointer; padding:6px; border-radius:7px; transition:color 0.2s; }
        .hamburger:hover { color:var(--text-warm); }
        .page-title { font-family:var(--font-head); font-size:1.15rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .back-link { font-size:0.82rem; color:var(--text-muted); text-decoration:none; display:flex; align-items:center; gap:6px; transition:color 0.2s; }
        .back-link:hover { color:var(--gold); }
        .content { padding:28px 32px; flex:1; max-width:760px; }

        /* ── ALERTS ── */
        .alert { display:flex; align-items:center; gap:10px; padding:13px 16px; border-radius:var(--radius-sm); font-size:0.875rem; margin-bottom:20px; }
        .alert-success { background:var(--teal-dim);   color:var(--teal);  border:1px solid rgba(0,212,170,0.22); }
        .alert-error   { background:var(--red-dim);    color:#ff7090;      border:1px solid rgba(255,77,109,0.22); }
        .alert-info    { background:var(--gold-dim);   color:var(--gold);  border:1px solid rgba(240,165,0,0.22); }

        /* ── STATUS HERO ── */
        .app-hero {
            background:var(--surface); border:1px solid var(--border2);
            border-radius:var(--radius-lg); padding:28px 32px; margin-bottom:24px;
            position:relative; overflow:hidden;
            opacity:0; animation:fadeUp 0.45s ease forwards;
        }
        .app-hero::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.3),transparent); }
        .app-hero::after  { content:''; position:absolute; top:-50px; right:-50px; width:200px; height:200px; background:radial-gradient(circle,rgba(160,100,255,0.12) 0%,transparent 65%); pointer-events:none; }

        .hero-inner { display:flex; align-items:center; gap:18px; position:relative; z-index:1; }
        .hero-icon  { width:60px; height:60px; border-radius:16px; background:var(--purple-dim); border:1px solid rgba(160,100,255,0.2); color:var(--purple); display:flex; align-items:center; justify-content:center; font-size:1.5rem; flex-shrink:0; }
        .hero-label { font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.12em; color:var(--purple); margin-bottom:4px; }
        .hero-title { font-family:var(--font-head); font-size:1.5rem; font-weight:800; color:var(--text); letter-spacing:-0.01em; line-height:1.1; margin-bottom:5px; }
        .hero-msg   { font-size:0.875rem; color:var(--text-muted); line-height:1.6; max-width:480px; }

        /* ── STATUS APP NOTICE ── */
        .status-notice {
            background:var(--surface); border-radius:var(--radius);
            overflow:hidden; margin-bottom:24px;
            opacity:0; animation:fadeUp 0.45s ease 0.1s forwards;
            position:relative;
        }
        .status-notice::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.15),transparent); }
        .sn-head { display:flex; align-items:center; gap:12px; padding:16px 20px; border-bottom:1px solid var(--border); }
        .sn-icon { width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:0.9rem; flex-shrink:0; }
        .sn-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .sn-body { padding:18px 20px; }
        .sn-body p { font-size:0.875rem; color:var(--text-muted); line-height:1.7; margin-bottom:10px; }
        .sn-body p:last-child { margin-bottom:0; }

        /* ── INFO CARDS ── */
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px; }
        .info-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:20px;
            opacity:0; animation:fadeUp 0.45s ease forwards;
            position:relative;
        }
        .info-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; border-radius:var(--radius) var(--radius) 0 0; }
        .info-card.ic-why::before  { background:linear-gradient(90deg,var(--gold),transparent); }
        .info-card.ic-req::before  { background:linear-gradient(90deg,var(--teal),transparent); }
        .info-card:nth-child(1){ animation-delay:.05s; }
        .info-card:nth-child(2){ animation-delay:.1s; }
        .ic-head { display:flex; align-items:center; gap:9px; margin-bottom:14px; }
        .ic-icon { width:28px; height:28px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.78rem; border:1px solid transparent; }
        .ic-gold   { background:var(--gold-dim);   color:var(--gold);   border-color:rgba(240,165,0,0.14); }
        .ic-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .ic-title { font-family:var(--font-head); font-size:0.92rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .ic-list { list-style:none; display:flex; flex-direction:column; gap:8px; }
        .ic-list li { display:flex; align-items:center; gap:9px; font-size:0.84rem; color:var(--text-muted); }
        .ic-list li i { font-size:0.7rem; width:14px; text-align:center; flex-shrink:0; }

        /* ── FORM PANEL ── */
        .form-panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden;
            opacity:0; animation:fadeUp 0.45s ease 0.15s forwards;
            position:relative;
        }
        .form-panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.18),transparent); z-index:1; }
        .form-head { display:flex; align-items:center; gap:10px; padding:15px 22px; border-bottom:1px solid var(--border); }
        .fh-icon  { width:28px; height:28px; border-radius:7px; background:var(--purple-dim); color:var(--purple); border:1px solid rgba(160,100,255,0.14); display:flex; align-items:center; justify-content:center; font-size:0.78rem; }
        .fh-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .form-body { padding:24px 22px; }

        /* ── FORM ELEMENTS ── */
        .form-group { display:flex; flex-direction:column; gap:6px; margin-bottom:18px; }
        .form-group:last-child { margin-bottom:0; }
        .form-label { font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); }
        .form-hint  { font-size:0.72rem; color:var(--text-dim); }

        textarea, input[type="text"] {
            width:100%; padding:11px 14px;
            background:var(--surface2); border:1px solid var(--border);
            border-radius:var(--radius-sm); color:var(--text-warm);
            font-family:var(--font-body); font-size:0.9rem;
            line-height:1.6; resize:vertical; outline:none;
            transition:all 0.22s;
        }
        textarea:focus, input[type="text"]:focus {
            border-color:var(--gold);
            box-shadow:0 0 0 3px rgba(240,165,0,0.1);
            background:var(--surface3);
        }
        textarea::placeholder { color:var(--text-dim); }

        .check-label { display:flex; align-items:flex-start; gap:10px; cursor:pointer; font-size:0.84rem; color:var(--text-muted); line-height:1.6; }
        input[type="checkbox"] { width:16px; height:16px; accent-color:var(--gold); flex-shrink:0; margin-top:3px; cursor:pointer; }

        /* ── BUTTONS ── */
        .btn { display:inline-flex; align-items:center; gap:7px; padding:11px 20px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.9rem; font-weight:600; text-decoration:none; cursor:pointer; border:none; transition:all 0.22s ease; letter-spacing:0.01em; }
        .btn-gold  { background:linear-gradient(135deg,var(--gold),#d48500); color:#0f0c08; font-weight:700; box-shadow:0 4px 18px var(--gold-glow); }
        .btn-gold:hover { background:linear-gradient(135deg,var(--gold-lt),var(--gold)); transform:translateY(-2px); box-shadow:0 8px 26px rgba(240,165,0,0.4); }
        .btn-ghost { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }
        .btn-ghost:hover { color:var(--text-warm); border-color:var(--border3); }
        .btn-purple { background:var(--purple-dim); color:var(--purple); border:1px solid rgba(160,100,255,0.2); }
        .btn-purple:hover { background:rgba(160,100,255,0.22); transform:translateY(-2px); }
        .btn-teal  { background:var(--teal-dim); color:var(--teal); border:1px solid rgba(0,212,170,0.2); }
        .btn-teal:hover  { background:rgba(0,212,170,0.2); transform:translateY(-2px); }

        .btn-row { display:flex; gap:10px; flex-wrap:wrap; margin-top:20px; }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:820px) { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
        @media(max-width:640px) { .info-grid{grid-template-columns:1fr;} .hero-inner{flex-direction:column;align-items:flex-start;} }
    </style>
</head>
<body>
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
            <?php if($role === 'seller'): ?>
                <div class="nav-label">Seller</div>
                <a href="seller-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="my-products.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-box-open"></i></span> My Products</a>
                <a href="add-product.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-plus-circle"></i></span> Add Product</a>
                <a href="my-transactions.php" class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span>Transactions</a>
                <a href="my-sales.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-chart-line"></i></span> Sales 
            <?php if($pending_tx_count > 0): ?><span class="nav-badge"><?php echo $pending_tx_count; ?></span><?php endif; ?></a>
                <a href="seller-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <?php else: ?>
                <div class="nav-label">Buyer</div>
                <a href="buyer-dashboard.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="products.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-store"></i></span> Browse Products</a>
                <a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> My Purchases</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span>Dispute Center</a>
            <?php endif; ?>
            <div class="nav-label" style="margin-top:10px;">Account</div>
            <a href="apply-midman.php" class="nav-link active"><span class="nav-icon"><i class="fas fa-user-check"></i></span> Apply as Midman</a>
            <a href="profile.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a>
            <a href="logout.php"       class="nav-link" style="color:var(--text-dim);margin-top:6px;"><span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="ava"><?php echo strtoupper(substr($_SESSION['username']??'GU',0,2)); ?></div>
                <div>
                    <div class="pill-name"><?php echo htmlspecialchars($display_name); ?></div>
                    <div class="pill-role"><?php echo ucfirst($role); ?></div>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <span class="page-title">Apply as Midman</span>
            </div>
            <a href="<?php echo $role=='seller'?'seller-dashboard.php':'buyer-dashboard.php'; ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
        </header>

        <div class="content">

            <?php if($success): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- HERO -->
            <div class="app-hero">
                <div class="hero-inner">
                    <div class="hero-icon"><i class="fas fa-handshake"></i></div>
                    <div>
                        <div class="hero-label">Midman Program</div>
                        <div class="hero-title">Apply to Become a Midman</div>
                        <div class="hero-msg">Join our trusted network of middlemen and earn by facilitating safe transactions for buyers and sellers in the community.</div>
                    </div>
                </div>
            </div>

            <!-- EXISTING APPLICATION STATUS -->
            <?php if($existing_app): ?>
            <div class="status-notice">
                <?php
                $app_status = $existing_app['status'];
                $sn_colors  = [
                    'pending'  => ['icon'=>'fa-clock',        'color'=>'var(--gold)',   'bg'=>'var(--gold-dim)',   'bc'=>'rgba(240,165,0,0.14)'],
                    'approved' => ['icon'=>'fa-circle-check', 'color'=>'var(--teal)',   'bg'=>'var(--teal-dim)',   'bc'=>'rgba(0,212,170,0.14)'],
                    'rejected' => ['icon'=>'fa-circle-xmark', 'color'=>'var(--red)',    'bg'=>'var(--red-dim)',    'bc'=>'rgba(255,77,109,0.14)'],
                ];
                $sc = $sn_colors[$app_status] ?? $sn_colors['pending'];
                ?>
                <div class="sn-head">
                    <div class="sn-icon" style="background:<?php echo $sc['bg']; ?>;color:<?php echo $sc['color']; ?>;border:1px solid <?php echo $sc['bc']; ?>;">
                        <i class="fas <?php echo $sc['icon']; ?>"></i>
                    </div>
                    <div class="sn-title" style="color:<?php echo $sc['color']; ?>">
                        Application <?php echo ucfirst($app_status); ?>
                    </div>
                </div>
                <div class="sn-body">
                    <p>You submitted an application on <strong style="color:var(--text-warm);"><?php echo date('F j, Y', strtotime($existing_app['created_at'])); ?></strong>.</p>
                    <?php if($app_status == 'pending'): ?>
                        <p>Your application is pending review. We'll notify you once an admin has processed it — this usually takes 1–3 business days.</p>
                    <?php elseif($app_status == 'approved'): ?>
                        <p>Congratulations! Your application was approved. You're now a verified Midman on the platform.</p>
                        <a href="midman-dashboard.php" class="btn btn-gold" style="margin-top:6px;"><i class="fas fa-gauge-high"></i> Go to Midman Dashboard</a>
                    <?php elseif($app_status == 'rejected'): ?>
                        <p>Your application was not approved this time. You're welcome to reapply with updated information below.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- WHY + REQUIREMENTS (only show when form is visible) -->
            <?php if(!$existing_app || $existing_app['status'] == 'rejected'): ?>
            <div class="info-grid">
                <div class="info-card ic-why">
                    <div class="ic-head">
                        <div class="ic-icon ic-gold"><i class="fas fa-star"></i></div>
                        <div class="ic-title">Why Become a Midman?</div>
                    </div>
                    <ul class="ic-list">
                        <li><i class="fas fa-coins" style="color:var(--gold);"></i> Earn fees on every transaction you facilitate</li>
                        <li><i class="fas fa-star" style="color:var(--gold);"></i> Build a trusted reputation in the community</li>
                        <li><i class="fas fa-handshake" style="color:var(--gold);"></i> Help buyers and sellers trade safely</li>
                        <li><i class="fas fa-arrow-trend-up" style="color:var(--gold);"></i> Unlock exclusive platform opportunities</li>
                    </ul>
                </div>
                <div class="info-card ic-req">
                    <div class="ic-head">
                        <div class="ic-icon ic-teal"><i class="fas fa-clipboard-check"></i></div>
                        <div class="ic-title">Requirements</div>
                    </div>
                    <ul class="ic-list">
                        <li><i class="fas fa-check" style="color:var(--teal);"></i> Verified identity (KYC completed)</li>
                        <li><i class="fas fa-check" style="color:var(--teal);"></i> Good standing in the community</li>
                        <li><i class="fas fa-check" style="color:var(--teal);"></i> Reliable and responsive communication</li>
                        <li><i class="fas fa-check" style="color:var(--teal);"></i> Basic understanding of online transactions</li>
                    </ul>
                </div>
            </div>

            <!-- APPLICATION FORM -->
            <div class="form-panel">
                <div class="form-head">
                    <div class="fh-icon"><i class="fas fa-file-pen"></i></div>
                    <span class="fh-title">Midman Application Form</span>
                </div>
                <div class="form-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Why do you want to become a Midman? <span style="color:var(--red);font-size:0.8rem;text-transform:none;letter-spacing:0;">*</span></label>
                            <textarea name="reason" rows="4" required placeholder="Tell us what motivates you to become a midman and how you plan to serve the community…"><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                            <span class="form-hint">Be specific — strong applications highlight your understanding of escrow and dispute resolution.</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Relevant Experience <span style="color:var(--red);font-size:0.8rem;text-transform:none;letter-spacing:0;">*</span></label>
                            <textarea name="experience" rows="4" required placeholder="Describe any experience you have with online transactions, trading, or dispute mediation…"><?php echo htmlspecialchars($_POST['experience'] ?? ''); ?></textarea>
                            <span class="form-hint">Prior experience as a buyer or seller on this platform is a plus.</span>
                        </div>
                        <div class="form-group">
                            <label class="check-label">
                                <input type="checkbox" required>
                                I confirm that all information provided is accurate and I agree to uphold the Midman code of conduct and terms of service.
                            </label>
                        </div>
                        <div class="btn-row">
                            <button type="submit" class="btn btn-gold"><i class="fas fa-paper-plane"></i> Submit Application</button>
                            <a href="<?php echo $role=='seller'?'seller-dashboard.php':'buyer-dashboard.php'; ?>" class="btn btn-ghost"><i class="fas fa-xmark"></i> Cancel</a>
                        </div>
                    </form>
                </div>
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