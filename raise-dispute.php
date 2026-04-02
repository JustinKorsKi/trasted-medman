<?php
require_once 'includes/config.php';
require_once 'includes/2fa-functions.php';

$pending_tx_count = 0;
if($_SESSION['role'] === 'seller') {
    $ptq = mysqli_query($conn, "SELECT COUNT(*) c FROM transactions WHERE seller_id={$_SESSION['user_id']} AND status='pending'");
    $pending_tx_count = mysqli_fetch_assoc($ptq)['c'];
} elseif($_SESSION['role'] === 'midman') {
    $ptq = mysqli_query($conn, "SELECT COUNT(*) c FROM transactions WHERE midman_id={$_SESSION['user_id']} AND status='pending'");
    $pending_tx_count = mysqli_fetch_assoc($ptq)['c'];
}



if(!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id   = $_SESSION['user_id'];
$role      = $_SESSION['role'];
$username  = $_SESSION['username'];
$full_name = $_SESSION['full_name'] ?? $_SESSION['username'];
$transaction_id = isset($_GET['transaction_id']) ? intval($_GET['transaction_id']) : 0;

$two_factor_enabled = ($role == 'midman') ? is2FAEnabled($user_id) : false;

/* ══════════════════════════════════════════════
   MODE A — No transaction_id → Dispute Center
══════════════════════════════════════════════ */
if(!$transaction_id) {

    $eligible = mysqli_query($conn,
        "SELECT t.*, p.title AS product_title, p.image_path, s.username AS seller_name
         FROM transactions t
         JOIN products p ON t.product_id = p.id
         JOIN users s ON t.seller_id = s.id
         WHERE (t.buyer_id=$user_id OR t.seller_id=$user_id OR t.midman_id=$user_id)
           AND t.status NOT IN ('disputed','cancelled')
           AND t.id NOT IN (SELECT transaction_id FROM disputes)
         ORDER BY t.created_at DESC");

    $existing = mysqli_query($conn,
        "SELECT t.*, p.title AS product_title, p.image_path, s.username AS seller_name,
                d.reason, d.status AS dispute_status, d.created_at AS dispute_date
         FROM transactions t
         JOIN products p ON t.product_id = p.id
         JOIN users s ON t.seller_id = s.id
         JOIN disputes d ON t.id = d.transaction_id
         WHERE (t.buyer_id=$user_id OR t.seller_id=$user_id OR t.midman_id=$user_id)
         ORDER BY d.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispute Center — Trusted Midman</title>
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
            --teal:       #00d4aa;
            --teal-dim:   rgba(0,212,170,0.11);
            --red:        #ff4d6d;
            --red-dim:    rgba(255,77,109,0.12);
            --orange:     #ff9632;
            --orange-dim: rgba(255,150,50,0.12);
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

            /* ── role accent — gold default (buyer/seller) ── */
            --accent:          #f0a500;
            --accent-lt:       #ffbe3a;
            --accent-dim:      rgba(240,165,0,0.13);
            --accent-glow:     rgba(240,165,0,0.28);
            --gradient-start:  #f0a500;
            --gradient-end:    #d4920a;
            --accent-fg:       #0f0c08;
        }

        /* ── midman override ── */
        body.role-midman {
            --accent:         #a064ff;
            --accent-lt:      #be8fff;
            --accent-dim:     rgba(160,100,255,0.13);
            --accent-glow:    rgba(160,100,255,0.28);
            --gradient-start: #a064ff;
            --gradient-end:   #7040cc;
            --accent-fg:      #ffffff;
        }

        html { scroll-behavior:smooth; }
        body { font-family:var(--font-body); background:var(--bg); color:var(--text-warm); min-height:100vh; overflow-x:hidden; -webkit-font-smoothing:antialiased; }
        .layout { display:flex; min-height:100vh; }

        @keyframes fadeUp { from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:translateY(0);} }

        /* ── SIDEBAR ── */
        .sidebar { width:var(--sidebar-w); background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh; z-index:100; transition:transform 0.35s cubic-bezier(.77,0,.18,1); }
        .sidebar::before { content:''; position:absolute; bottom:-80px; left:-80px; width:300px; height:300px; background:radial-gradient(circle,rgba(200,100,0,0.08) 0%,transparent 65%); pointer-events:none; }
        body.role-midman .sidebar::before { background:radial-gradient(circle,rgba(120,60,200,0.09) 0%,transparent 65%); }
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


        .logo-sub  { font-size:0.65rem; color:var(--accent); letter-spacing:0.12em; text-transform:uppercase; display:block; font-family:var(--font-body); font-weight:600; }
        .sidebar-nav { flex:1; padding:18px 10px; overflow-y:auto; position:relative; z-index:1; }
        .nav-label { font-size:0.65rem; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:var(--text-dim); padding:12px 12px 7px; }
        .nav-link { display:flex; align-items:center; gap:11px; padding:10px 13px; border-radius:var(--radius-sm); text-decoration:none; color:var(--text-muted); font-size:0.9rem; font-weight:500; margin-bottom:2px; transition:all 0.2s; position:relative; }
        .nav-link:hover { color:var(--text-warm); background:var(--surface2); }
        .nav-link.active { color:var(--accent); background:var(--accent-dim); border:1px solid rgba(255,255,255,0.06); }
        .nav-link.active::before { content:''; position:absolute; left:0; top:20%; bottom:20%; width:3px; background:var(--accent); border-radius:0 3px 3px 0; }
        .nav-icon { width:20px; text-align:center; font-size:0.9rem; flex-shrink:0; }
        .nav-badge { margin-left:auto; background:var(--red-dim); color:var(--red); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(255,77,109,0.15); }
        .security-badge { margin-left:auto; background:var(--teal-dim); color:var(--teal); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(0,212,170,0.15); }
        .sidebar-footer { padding:14px; border-top:1px solid var(--border); position:relative; z-index:1; }
        .user-pill { display:flex; align-items:center; gap:10px; padding:10px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); }
        .avatar { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.85rem; color:var(--accent-fg); flex-shrink:0; box-shadow:0 0 10px var(--accent-glow); }
        .user-pill-name { font-size:0.875rem; font-weight:500; color:var(--text-warm); }
        .user-pill-role { font-size:0.68rem; color:var(--accent); text-transform:uppercase; letter-spacing:0.09em; }

        /* ── MAIN ── */
        .main { margin-left:var(--sidebar-w); flex:1; min-height:100vh; display:flex; flex-direction:column; }
        .topbar { position:sticky; top:0; z-index:50; background:rgba(15,12,8,0.88); backdrop-filter:blur(24px); border-bottom:1px solid var(--border); padding:0 32px; height:64px; display:flex; align-items:center; justify-content:space-between; }
        .topbar-left { display:flex; align-items:center; gap:16px; }
        .hamburger { display:none; background:none; border:none; color:var(--text-muted); font-size:1.1rem; cursor:pointer; padding:6px; border-radius:7px; transition:color 0.2s; }
        .hamburger:hover { color:var(--text-warm); }
        .page-title { font-family:var(--font-head); font-size:1.15rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .topbar-right { display:flex; align-items:center; gap:10px; }
        .topbar-btn { width:36px; height:36px; background:var(--surface2); border:1px solid var(--border); border-radius:9px; display:flex; align-items:center; justify-content:center; color:var(--text-muted); cursor:pointer; transition:all 0.2s; text-decoration:none; }
        .topbar-btn:hover { color:var(--accent); border-color:var(--accent-dim); background:var(--accent-dim); }
        .online-dot { display:flex; align-items:center; gap:7px; font-size:0.78rem; color:var(--text-muted); }
        .online-dot::before { content:''; width:7px; height:7px; border-radius:50%; background:var(--accent); box-shadow:0 0 8px var(--accent-glow); }
        .content { padding:32px; flex:1; }

        /* ── HERO ── */
        .hero {
            background:var(--surface); border:1px solid var(--border2);
            border-radius:var(--radius-lg); padding:36px 44px; margin-bottom:26px;
            position:relative; overflow:hidden;
            animation:fadeUp 0.45s ease forwards;
        }
        .hero::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(255,77,109,0.35),transparent); }
        .hero-glow { position:absolute; top:-60px; right:-60px; width:320px; height:320px; background:radial-gradient(circle,rgba(255,77,109,0.12) 0%,transparent 65%); pointer-events:none; }
        .hero-inner { position:relative; z-index:1; display:flex; align-items:center; justify-content:space-between; gap:24px; flex-wrap:wrap; }
        .hero-eyebrow { font-size:0.75rem; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:var(--red); margin-bottom:10px; display:flex; align-items:center; gap:8px; }
        .hero-eyebrow::before { content:''; width:24px; height:2px; background:var(--red); border-radius:2px; }
        .hero-title { font-family:var(--font-head); font-size:2.3rem; font-weight:800; color:var(--text); line-height:1.08; margin-bottom:10px; letter-spacing:-0.01em; }
        .hero-title span { color:var(--red); }
        .hero-desc { color:var(--text-muted); font-size:0.92rem; line-height:1.75; max-width:500px; }
        .hero-badge { flex-shrink:0; width:110px; height:110px; background:var(--surface2); border:1px solid var(--border2); border-radius:50%; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px; }
        .hero-badge-icon { font-size:2rem; color:var(--red); }
        .hero-badge-label { font-size:0.62rem; font-weight:700; text-transform:uppercase; letter-spacing:0.12em; color:var(--text-muted); }

        /* ── NOTICE ── */
        .notice { background:rgba(255,150,50,0.06); border:1px solid rgba(255,150,50,0.18); border-radius:var(--radius-sm); padding:16px 20px; margin-bottom:28px; display:flex; gap:14px; align-items:flex-start; }
        .notice-icon { color:var(--orange); font-size:1rem; margin-top:2px; flex-shrink:0; }
        .notice-text { font-size:0.84rem; color:var(--text-muted); line-height:1.75; }
        .notice-text strong { color:var(--text-warm); }

        /* ── SECTION HEADERS ── */
        .section-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
        .section-title { font-family:var(--font-head); font-size:1.05rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:10px; letter-spacing:-0.01em; }
        .section-icon { width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; border:1px solid transparent; }
        .si-red  { background:var(--red-dim);     color:var(--red);    border-color:rgba(255,77,109,0.14); }
        .si-acc  { background:var(--accent-dim);  color:var(--accent); border-color:rgba(255,255,255,0.06); }
        .section-count { font-size:0.75rem; font-weight:600; padding:3px 10px; border-radius:20px; background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }

        /* ── TRANSACTION CARDS ── */
        .txn-grid { display:grid; gap:12px; margin-bottom:32px; }
        .txn-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); padding:18px 22px;
            display:flex; align-items:center; gap:18px;
            transition:all 0.25s ease; animation:fadeUp 0.4s ease both;
            position:relative;
        }
        .txn-card::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.1),transparent); }
        .txn-card:hover { border-color:var(--border2); transform:translateY(-2px); box-shadow:0 12px 32px rgba(0,0,0,0.4); }
        .txn-thumb { width:56px; height:56px; border-radius:var(--radius-sm); background:var(--surface2); border:1px solid var(--border); flex-shrink:0; display:flex; align-items:center; justify-content:center; color:var(--text-dim); font-size:1.3rem; overflow:hidden; }
        .txn-thumb img { width:100%; height:100%; object-fit:cover; }
        .txn-body { flex:1; min-width:0; }
        .txn-name { font-size:0.95rem; font-weight:600; color:var(--text-warm); margin-bottom:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .txn-meta { display:flex; flex-wrap:wrap; gap:14px; font-size:0.78rem; color:var(--text-muted); }
        .txn-meta span { display:flex; align-items:center; gap:5px; }
        .txn-meta i { color:var(--text-dim); font-size:0.72rem; }
        .txn-right { display:flex; flex-direction:column; align-items:flex-end; gap:10px; flex-shrink:0; }
        .txn-amount { font-family:var(--font-head); font-size:1.1rem; font-weight:800; color:var(--accent); letter-spacing:-0.01em; }

        .btn-dispute { display:inline-flex; align-items:center; gap:7px; padding:9px 18px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.82rem; font-weight:700; text-decoration:none; background:var(--red-dim); color:var(--red); border:1px solid rgba(255,77,109,0.2); transition:all 0.25s; white-space:nowrap; }
        .btn-dispute:hover { background:var(--red); color:#fff; transform:translateY(-1px); box-shadow:0 6px 20px rgba(255,77,109,0.35); }

        /* ── EXISTING DISPUTE CARDS ── */
        .dispute-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:18px 22px; display:flex; align-items:center; gap:18px; margin-bottom:12px; transition:border-color 0.25s; animation:fadeUp 0.4s ease both; }
        .dispute-card:hover { border-color:var(--border2); }
        .dispute-body { flex:1; min-width:0; }
        .dispute-reason { font-size:0.72rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.09em; margin-bottom:4px; }
        .dispute-name { font-size:0.95rem; font-weight:600; color:var(--text-warm); margin-bottom:5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .dispute-date { font-size:0.75rem; color:var(--text-dim); }

        /* ── BADGES ── */
        .badge { display:inline-block; font-size:0.65rem; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; padding:3px 9px; border-radius:20px; }
        .badge-pending        { background:var(--accent-dim);  color:var(--accent); border:1px solid rgba(255,255,255,0.06); }
        .badge-in_progress    { background:var(--blue-dim);    color:var(--blue);   border:1px solid rgba(78,159,255,0.14); }
        .badge-completed      { background:var(--teal-dim);    color:var(--teal);   border:1px solid rgba(0,212,170,0.14); }
        .badge-open           { background:var(--red-dim);     color:var(--red);    border:1px solid rgba(255,77,109,0.14); }
        .badge-resolved       { background:var(--teal-dim);    color:var(--teal);   border:1px solid rgba(0,212,170,0.14); }
        .badge-under_review   { background:var(--orange-dim);  color:var(--orange); border:1px solid rgba(255,150,50,0.14); }

        /* ── EMPTY ── */
        .empty { text-align:center; padding:48px 24px; color:var(--text-muted); }
        .empty i  { font-size:2.8rem; margin-bottom:16px; opacity:0.2; display:block; color:var(--text-dim); }
        .empty h4 { color:var(--text-warm); font-family:var(--font-head); font-size:1.1rem; margin-bottom:8px; letter-spacing:-0.01em; }
        .empty p  { font-size:0.85rem; margin-bottom:20px; }
        .btn-primary { display:inline-flex; align-items:center; gap:8px; padding:11px 22px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.9rem; font-weight:700; text-decoration:none; background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); color:var(--accent-fg); box-shadow:0 4px 20px var(--accent-glow); transition:all 0.25s; }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 28px var(--accent-glow); }



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


        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:820px) { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} .hero{padding:26px 22px;} .hero-title{font-size:1.75rem;} .hero-badge{display:none;} .txn-card{flex-wrap:wrap;} .txn-right{flex-direction:row;align-items:center;width:100%;} }
    </style>
</head>
<body class="role-<?php echo $role; ?>">
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <!-- SIDEBAR MODE A -->
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
                <a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> Transactions</a>
                <a href="my-sales.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-chart-line"></i></span> Sales 
            <?php if($pending_tx_count > 0): ?><span class="nav-badge"><?php echo $pending_tx_count; ?></span><?php endif; ?></a>
                <a href="seller-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
                <a href="raise-dispute.php"    class="nav-link active"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <?php elseif($role == 'midman'): ?>
                <div class="nav-label">Midman</div>
                <a href="midman-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-handshake"></i></span> Transactions
            <?php if($pending_tx_count > 0): ?><span class="nav-badge"><?php echo $pending_tx_count; ?></span><?php endif; ?></a>
                <a href="midman-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
                <a href="raise-dispute.php"    class="nav-link active"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
                <a href="verify-identity.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span> KYC Status</a>
                <div class="nav-label" style="margin-top:10px;">Security</div>
                <a href="setup-2fa.php" class="nav-link">
                    <span class="nav-icon"><i class="fas fa-shield-alt"></i></span>
                    <?php echo $two_factor_enabled ? 'Manage 2FA' : 'Enable 2FA'; ?>
                    <?php if($two_factor_enabled): ?><span class="security-badge">Active</span><?php endif; ?>
                </a>
            <?php else: ?>
                <div class="nav-label">Buyer</div>
                <a href="buyer-dashboard.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="products.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-store"></i></span> Browse Products</a>
                <a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> My Purchases</a>
                <a href="raise-dispute.php"    class="nav-link active"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <?php endif; ?>
            <div class="nav-label" style="margin-top:10px;">Account</div>
            <?php if($role !== 'midman'): ?>
                <a href="apply-midman.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span> Apply as Midman</a>
            <?php endif; ?>
            <a href="profile.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a>
            <a href="logout.php"  class="nav-link" style="color:var(--text-dim);margin-top:6px;"><span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="avatar"><?php echo strtoupper(substr($username,0,2)); ?></div>
                <div>
                    <div class="user-pill-name"><?php echo htmlspecialchars($full_name); ?></div>
                    <div class="user-pill-role"><?php echo ucfirst($role); ?></div>
                </div>
            </div>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <span class="page-title">Dispute Center</span>
            </div>
            <div class="topbar-right">
                <a href="my-transactions.php" class="topbar-btn" title="My Orders"><i class="fas fa-bag-shopping" style="font-size:0.85rem;"></i></a>
                <div class="online-dot">Online</div>
            </div>
        </header>

        <div class="content">

            <!-- HERO -->
            <div class="hero">
                <div class="hero-glow"></div>
                <div class="hero-inner">
                    <div>
                        <div class="hero-eyebrow">Dispute Center</div>
                        <h1 class="hero-title">Need help with<br>a <span>transaction?</span></h1>
                        <p class="hero-desc">Select an eligible order below to raise a dispute. Our midman team reviews every case and works to get you a fair resolution.</p>
                    </div>
                    <div class="hero-badge">
                        <div class="hero-badge-icon"><i class="fas fa-scale-balanced"></i></div>
                        <div class="hero-badge-label">Fair Review</div>
                    </div>
                </div>
            </div>

            <!-- NOTICE -->
            <div class="notice">
                <i class="fas fa-triangle-exclamation notice-icon"></i>
                <div class="notice-text">
                    <strong>Before raising a dispute:</strong>
                    Try resolving the issue directly with the other party first. Disputes are for serious issues that cannot be resolved informally. False or malicious disputes may result in account penalties.
                </div>
            </div>

            <!-- ELIGIBLE -->
            <div class="section-head">
                <div class="section-title">
                    <div class="section-icon si-red"><i class="fas fa-circle-exclamation"></i></div>
                    Eligible Orders
                </div>
                <span class="section-count"><?php echo mysqli_num_rows($eligible); ?> available</span>
            </div>
            <div class="txn-grid">
                <?php if(mysqli_num_rows($eligible) > 0):
                    $i = 0;
                    while($t = mysqli_fetch_assoc($eligible)):
                        $delay = $i * 0.07; $i++;
                ?>
                <div class="txn-card" style="animation-delay:<?php echo $delay; ?>s">
                    <div class="txn-thumb">
                        <?php if($t['image_path']): ?><img src="<?php echo htmlspecialchars($t['image_path']); ?>" alt=""><?php else: ?><i class="fas fa-gamepad"></i><?php endif; ?>
                    </div>
                    <div class="txn-body">
                        <div class="txn-name"><?php echo htmlspecialchars($t['product_title']); ?></div>
                        <div class="txn-meta">
                            <span><i class="fas fa-user"></i><?php echo htmlspecialchars($t['seller_name']); ?></span>
                            <span><i class="fas fa-calendar"></i><?php echo date('M d, Y', strtotime($t['created_at'])); ?></span>
                            <span><span class="badge badge-<?php echo $t['status']; ?>"><?php echo ucfirst($t['status']); ?></span></span>
                        </div>
                    </div>
                    <div class="txn-right">
                        <div class="txn-amount">$<?php echo number_format($t['amount'],2); ?></div>
                        <a href="raise-dispute.php?transaction_id=<?php echo $t['id']; ?>" class="btn-dispute">
                            <i class="fas fa-scale-balanced"></i> Raise Dispute
                        </a>
                    </div>
                </div>
                <?php endwhile; else: ?>
                <div class="empty">
                    <i class="fas fa-circle-check"></i>
                    <h4>All Clear!</h4>
                    <p>You have no eligible transactions to dispute right now.</p>
                    <a href="my-transactions.php" class="btn-primary"><i class="fas fa-bag-shopping"></i> View My Orders</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- EXISTING DISPUTES -->
            <div class="section-head">
                <div class="section-title">
                    <div class="section-icon si-acc"><i class="fas fa-clock-rotate-left"></i></div>
                    My Disputes
                </div>
                <span class="section-count"><?php echo mysqli_num_rows($existing); ?> total</span>
            </div>

            <?php if(mysqli_num_rows($existing) > 0):
                $j = 0;
                while($d = mysqli_fetch_assoc($existing)):
                    $delay2 = $j * 0.07; $j++;
            ?>
            <div class="dispute-card" style="animation-delay:<?php echo $delay2; ?>s">
                <div class="txn-thumb">
                    <?php if($d['image_path']): ?><img src="<?php echo htmlspecialchars($d['image_path']); ?>" alt=""><?php else: ?><i class="fas fa-gamepad"></i><?php endif; ?>
                </div>
                <div class="dispute-body">
                    <div class="dispute-reason"><?php echo htmlspecialchars(ucwords(str_replace('_',' ',$d['reason']))); ?></div>
                    <div class="dispute-name"><?php echo htmlspecialchars($d['product_title']); ?></div>
                    <div class="dispute-date">Raised <?php echo date('M d, Y', strtotime($d['dispute_date'])); ?> &bull; Seller: <?php echo htmlspecialchars($d['seller_name']); ?></div>
                </div>
                <div class="txn-right">
                    <div class="txn-amount">$<?php echo number_format($d['amount'],2); ?></div>
                    <span class="badge badge-<?php echo $d['dispute_status']; ?>"><?php echo ucfirst(str_replace('_',' ',$d['dispute_status'])); ?></span>
                </div>
            </div>
            <?php endwhile; else: ?>
            <div class="empty">
                <i class="fas fa-folder-open"></i>
                <h4>No disputes yet</h4>
                <p>Any disputes you raise will appear here for tracking.</p>
            </div>
            <?php endif; ?>

        </div>
    </main>
</div>
<script>
    const hamburger = document.getElementById('hamburger');
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('overlay');
    function openSidebar()  { sidebar.classList.add('open');    overlay.classList.add('visible'); }
    function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('visible'); }
    hamburger.addEventListener('click', () => sidebar.classList.contains('open') ? closeSidebar() : openSidebar());
    overlay.addEventListener('click', closeSidebar);
</script>
</body>
</html>
<?php
    exit(); // End MODE A
}

/* ══════════════════════════════════════════════
   MODE B — transaction_id → Raise Dispute Form
══════════════════════════════════════════════ */
$query = "SELECT t.*, p.title as product_title, p.image_path,
          b.username as buyer_name, s.username as seller_name
          FROM transactions t
          JOIN products p ON t.product_id = p.id
          JOIN users b ON t.buyer_id = b.id
          JOIN users s ON t.seller_id = s.id
          WHERE t.id = $transaction_id";
$result = mysqli_query($conn, $query);
if(mysqli_num_rows($result) == 0) { header('Location: raise-dispute.php'); exit(); }
$transaction = mysqli_fetch_assoc($result);

if($transaction['buyer_id']!=$user_id && $transaction['seller_id']!=$user_id && $transaction['midman_id']!=$user_id) {
    header('Location: raise-dispute.php'); exit();
}
$check = mysqli_query($conn, "SELECT * FROM disputes WHERE transaction_id=$transaction_id");
if(mysqli_num_rows($check) > 0) {
    $_SESSION['error'] = 'A dispute has already been raised for this transaction.';
    header("Location: transaction-detail.php?id=$transaction_id"); exit();
}

$error = $success = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $reason      = mysqli_real_escape_string($conn, $_POST['reason']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    if(empty($reason) || empty($description)) {
        $error = 'Please fill in all required fields.';
    } else {
        $q = "INSERT INTO disputes (transaction_id, raised_by, reason, description, status) VALUES ($transaction_id, $user_id, '$reason', '$description', 'open')";
        if(mysqli_query($conn, $q)) {
            mysqli_query($conn, "UPDATE transactions SET status='disputed' WHERE id=$transaction_id");
            $_SESSION['success'] = 'Dispute raised successfully. An admin will review your case.';
            header("Location: transaction-detail.php?id=$transaction_id"); exit();
        } else {
            $error = 'Failed to raise dispute. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raise Dispute — Trusted Midman</title>
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
            --teal:       #00d4aa;
            --teal-dim:   rgba(0,212,170,0.11);
            --red:        #ff4d6d;
            --red-dim:    rgba(255,77,109,0.12);
            --orange:     #ff9632;
            --orange-dim: rgba(255,150,50,0.12);
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

            --accent:         #f0a500;
            --accent-lt:      #ffbe3a;
            --accent-dim:     rgba(240,165,0,0.13);
            --accent-glow:    rgba(240,165,0,0.28);
            --gradient-start: #f0a500;
            --gradient-end:   #d4920a;
            --accent-fg:      #0f0c08;
        }
        body.role-midman {
            --accent:         #a064ff;
            --accent-lt:      #be8fff;
            --accent-dim:     rgba(160,100,255,0.13);
            --accent-glow:    rgba(160,100,255,0.28);
            --gradient-start: #a064ff;
            --gradient-end:   #7040cc;
            --accent-fg:      #ffffff;
        }

        html { scroll-behavior:smooth; }
        body { font-family:var(--font-body); background:var(--bg); color:var(--text-warm); min-height:100vh; overflow-x:hidden; -webkit-font-smoothing:antialiased; }
        .layout { display:flex; min-height:100vh; }

        @keyframes fadeUp { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);} }

        /* ── SIDEBAR (same as Mode A) ── */
        .sidebar { width:var(--sidebar-w); background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh; z-index:100; transition:transform 0.35s cubic-bezier(.77,0,.18,1); }
        .sidebar::before { content:''; position:absolute; bottom:-80px; left:-80px; width:300px; height:300px; background:radial-gradient(circle,rgba(200,100,0,0.08) 0%,transparent 65%); pointer-events:none; }
        body.role-midman .sidebar::before { background:radial-gradient(circle,rgba(120,60,200,0.09) 0%,transparent 65%); }
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
        .nav-link.active { color:var(--accent); background:var(--accent-dim); border:1px solid rgba(255,255,255,0.06); }
        .nav-link.active::before { content:''; position:absolute; left:0; top:20%; bottom:20%; width:3px; background:var(--accent); border-radius:0 3px 3px 0; }
        .nav-icon { width:20px; text-align:center; font-size:0.9rem; flex-shrink:0; }
        .security-badge { margin-left:auto; background:var(--teal-dim); color:var(--teal); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(0,212,170,0.15); }
        .sidebar-footer { padding:14px; border-top:1px solid var(--border); position:relative; z-index:1; }
        .user-pill { display:flex; align-items:center; gap:10px; padding:10px 12px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); }
        .avatar { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--gradient-start),var(--gradient-end)); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.85rem; color:var(--accent-fg); flex-shrink:0; box-shadow:0 0 10px var(--accent-glow); }
        .user-pill-name { font-size:0.875rem; font-weight:500; color:var(--text-warm); }
        .user-pill-role { font-size:0.68rem; color:var(--accent); text-transform:uppercase; letter-spacing:0.09em; }

        /* ── MAIN ── */
        .main { margin-left:var(--sidebar-w); flex:1; min-height:100vh; display:flex; flex-direction:column; }
        .topbar { position:sticky; top:0; z-index:50; background:rgba(15,12,8,0.88); backdrop-filter:blur(24px); border-bottom:1px solid var(--border); padding:0 32px; height:64px; display:flex; align-items:center; justify-content:space-between; }
        .topbar-left { display:flex; align-items:center; gap:16px; }
        .hamburger { display:none; background:none; border:none; color:var(--text-muted); font-size:1.1rem; cursor:pointer; padding:6px; border-radius:7px; }
        .page-title { font-family:var(--font-head); font-size:1.15rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .topbar-right { display:flex; align-items:center; gap:10px; }
        .topbar-btn { width:36px; height:36px; background:var(--surface2); border:1px solid var(--border); border-radius:9px; display:flex; align-items:center; justify-content:center; color:var(--text-muted); text-decoration:none; transition:all 0.2s; }
        .topbar-btn:hover { color:var(--accent); background:var(--accent-dim); border-color:var(--accent-dim); }
        .online-dot { display:flex; align-items:center; gap:7px; font-size:0.78rem; color:var(--text-muted); }
        .online-dot::before { content:''; width:7px; height:7px; border-radius:50%; background:var(--accent); box-shadow:0 0 8px var(--accent-glow); }
        .content { padding:28px 32px; flex:1; max-width:760px; }

        /* ── BREADCRUMB ── */
        .breadcrumb { display:flex; align-items:center; gap:8px; font-size:0.78rem; color:var(--text-dim); margin-bottom:22px; }
        .breadcrumb a { color:var(--text-muted); text-decoration:none; transition:color 0.2s; }
        .breadcrumb a:hover { color:var(--accent); }
        .breadcrumb i { font-size:0.6rem; }

        /* ── ALERTS ── */
        .alert { display:flex; align-items:center; gap:10px; padding:13px 16px; border-radius:var(--radius-sm); font-size:0.875rem; margin-bottom:20px; }
        .alert-error { background:var(--red-dim); color:#ff7090; border:1px solid rgba(255,77,109,0.22); }

        /* ── TRANSACTION PREVIEW ── */
        .txn-preview {
            background:var(--surface); border:1px solid var(--border2);
            border-radius:var(--radius); padding:18px 20px; margin-bottom:20px;
            display:flex; align-items:center; gap:16px;
            position:relative;
        }
        .txn-preview::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,var(--accent-dim),transparent); }
        .txn-preview-thumb { width:60px; height:60px; border-radius:10px; background:var(--surface2); border:1px solid var(--border); overflow:hidden; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:var(--text-dim); font-size:1.4rem; }
        .txn-preview-thumb img { width:100%; height:100%; object-fit:cover; }
        .txn-preview-body { flex:1; min-width:0; }
        .txn-preview-title { font-family:var(--font-head); font-size:1rem; font-weight:700; color:var(--text-warm); margin-bottom:5px; letter-spacing:-0.01em; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .txn-preview-meta  { display:flex; flex-wrap:wrap; gap:12px; font-size:0.78rem; color:var(--text-muted); }
        .txn-preview-meta span { display:flex; align-items:center; gap:5px; }
        .txn-preview-meta i { font-size:0.72rem; color:var(--text-dim); }
        .txn-preview-amount { font-family:var(--font-head); font-size:1.2rem; font-weight:800; color:var(--accent); letter-spacing:-0.01em; flex-shrink:0; }

        /* ── NOTICE ── */
        .notice { background:rgba(255,150,50,0.06); border:1px solid rgba(255,150,50,0.18); border-radius:var(--radius-sm); padding:16px 20px; margin-bottom:22px; display:flex; gap:14px; align-items:flex-start; }
        .notice-icon { color:var(--orange); font-size:1rem; margin-top:2px; flex-shrink:0; }
        .notice-text { font-size:0.84rem; color:var(--text-muted); line-height:1.75; }
        .notice-text strong { color:var(--text-warm); }
        .notice-text ul { margin-top:6px; padding-left:16px; display:flex; flex-direction:column; gap:4px; }

        /* ── FORM PANELS ── */
        .panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden; margin-bottom:16px;
            opacity:0; animation:fadeUp 0.45s ease forwards;
            position:relative;
        }
        .panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.12),transparent); z-index:1; }
        .panel:nth-child(1){animation-delay:0.05s;} .panel:nth-child(2){animation-delay:0.1s;} .panel:nth-child(3){animation-delay:0.15s;}
        .panel-header { display:flex; align-items:center; gap:10px; padding:15px 20px; border-bottom:1px solid var(--border); }
        .panel-header-icon { width:28px; height:28px; border-radius:7px; background:var(--red-dim); color:var(--red); border:1px solid rgba(255,77,109,0.14); display:flex; align-items:center; justify-content:center; font-size:0.78rem; }
        .panel-header-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .panel-body { padding:20px; }

        /* ── REASON GRID ── */
        .reason-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .reason-opt { cursor:pointer; }
        .reason-opt input[type="radio"] { display:none; }
        .reason-box {
            display:flex; align-items:flex-start; gap:12px;
            padding:14px 16px; background:var(--surface2); border:2px solid var(--border);
            border-radius:var(--radius-sm); transition:all 0.22s; height:100%;
        }
        .reason-opt input:checked + .reason-box { border-color:var(--red); background:var(--red-dim); }
        .reason-box:hover { border-color:var(--border2); background:var(--surface3); }
        .reason-radio { width:16px; height:16px; border-radius:50%; border:2px solid var(--border2); flex-shrink:0; margin-top:2px; transition:all 0.2s; }
        .reason-opt input:checked + .reason-box .reason-radio { border-color:var(--red); background:var(--red); box-shadow:0 0 0 3px rgba(255,77,109,0.15); }
        .reason-title { font-size:0.88rem; font-weight:600; color:var(--text-warm); margin-bottom:3px; }
        .reason-desc  { font-size:0.75rem; color:var(--text-muted); line-height:1.5; }

        /* ── FORM ELEMENTS ── */
        .form-label { font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); display:block; margin-bottom:8px; }
        .form-label span { color:var(--red); font-weight:400; text-transform:none; letter-spacing:0; }
        .form-textarea {
            width:100%; padding:12px 14px; background:var(--surface2);
            border:1px solid var(--border); border-radius:var(--radius-sm);
            color:var(--text-warm); font-family:var(--font-body); font-size:0.9rem;
            resize:vertical; min-height:120px; line-height:1.6; outline:none;
            transition:all 0.22s;
        }
        .form-textarea:focus { border-color:var(--red); box-shadow:0 0 0 3px rgba(255,77,109,0.1); background:var(--surface3); }
        .form-textarea::placeholder { color:var(--text-dim); }

        /* ── FILE ZONE ── */
        .file-zone {
            border:2px dashed var(--border2); border-radius:var(--radius);
            padding:28px 20px; text-align:center; cursor:pointer; transition:all 0.25s;
            position:relative;
        }
        .file-zone:hover { border-color:var(--accent); background:var(--accent-dim); }
        .file-zone i  { font-size:2rem; color:var(--text-dim); margin-bottom:8px; display:block; transition:color 0.2s; }
        .file-zone:hover i { color:var(--accent); }
        .file-zone p  { font-size:0.875rem; font-weight:600; color:var(--text-muted); margin-bottom:4px; }
        .file-zone small { font-size:0.72rem; color:var(--text-dim); }
        .file-zone input[type="file"] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }

        /* ── ACTION ROW ── */
        .action-row { display:flex; gap:12px; flex-wrap:wrap; margin-top:4px; }
        .btn { display:inline-flex; align-items:center; gap:8px; padding:11px 22px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.9rem; font-weight:600; text-decoration:none; cursor:pointer; border:none; transition:all 0.25s; letter-spacing:0.01em; }
        .btn-danger { background:var(--red-dim); color:var(--red); border:1px solid rgba(255,77,109,0.25); }
        .btn-danger:hover { background:var(--red); color:#fff; transform:translateY(-2px); box-shadow:0 6px 20px rgba(255,77,109,0.35); }
        .btn-ghost  { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }
        .btn-ghost:hover  { color:var(--text-warm); border-color:var(--border3); }

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
        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:820px) { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
        @media(max-width:600px) { .reason-grid{grid-template-columns:1fr;} }
    </style>
</head>
<body class="role-<?php echo $role; ?>">
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <!-- SIDEBAR MODE B -->
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
                <a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> Transactions</a>
                <a href="my-sales.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-chart-line"></i></span> Sales</a>
                <a href="seller-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
                <a href="raise-dispute.php"    class="nav-link active"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <?php elseif($role === 'midman'): ?>
                <div class="nav-label">Midman</div>
                <a href="midman-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-handshake"></i></span> Transactions</a>
                <a href="midman-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
                <a href="raise-dispute.php"    class="nav-link active"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
                <a href="verify-identity.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span> KYC Status</a>
                <div class="nav-label" style="margin-top:10px;">Security</div>
                <a href="setup-2fa.php" class="nav-link">
                    <span class="nav-icon"><i class="fas fa-shield-alt"></i></span>
                    <?php echo $two_factor_enabled ? 'Manage 2FA' : 'Enable 2FA'; ?>
                    <?php if($two_factor_enabled): ?><span class="security-badge">Active</span><?php endif; ?>
                </a>
            <?php else: ?>
                <div class="nav-label">Buyer</div>
                <a href="buyer-dashboard.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="products.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-store"></i></span> Browse Products</a>
                <a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> My Purchases</a>
                <a href="raise-dispute.php"    class="nav-link active"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <?php endif; ?>
            <div class="nav-label" style="margin-top:10px;">Account</div>
            <?php if($role !== 'midman'): ?>
                <a href="apply-midman.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span> Apply as Midman</a>
            <?php endif; ?>
            <a href="profile.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a>
            <a href="logout.php"  class="nav-link" style="color:var(--text-dim);margin-top:6px;"><span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="avatar"><?php echo strtoupper(substr($username,0,2)); ?></div>
                <div>
                    <div class="user-pill-name"><?php echo htmlspecialchars($full_name); ?></div>
                    <div class="user-pill-role"><?php echo ucfirst($role); ?></div>
                </div>
            </div>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <span class="page-title">Raise Dispute</span>
            </div>
            <!-- <div class="topbar-right">
                <a href="raise-dispute.php" class="topbar-btn" title="Back to Dispute Center"><i class="fas fa-arrow-left" style="font-size:0.85rem;"></i></a>
                <div class="online-dot">Online</div>
            </div> -->
        </header>

        <div class="content">

            <!-- BREADCRUMB -->
            <div class="breadcrumb">
                <a href="<?php echo $role=='seller'?'seller-dashboard.php':($role=='midman'?'midman-dashboard.php':'buyer-dashboard.php'); ?>">Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <a href="raise-dispute.php">Dispute Center</a>
                <i class="fas fa-chevron-right"></i>
                <span>Raise Dispute</span>
            </div>

            <!-- TRANSACTION PREVIEW -->
            <div class="txn-preview">
                <div class="txn-preview-thumb">
                    <?php if($transaction['image_path']): ?><img src="<?php echo htmlspecialchars($transaction['image_path']); ?>" alt=""><?php else: ?><i class="fas fa-gamepad"></i><?php endif; ?>
                </div>
                <div class="txn-preview-body">
                    <div class="txn-preview-title"><?php echo htmlspecialchars($transaction['product_title']); ?></div>
                    <div class="txn-preview-meta">
                        <span><i class="fas fa-user"></i>
                            <?php echo $role=='buyer' ? 'Seller: '.htmlspecialchars($transaction['seller_name']) : 'Buyer: '.htmlspecialchars($transaction['buyer_name']); ?>
                        </span>
                        <span><i class="fas fa-calendar"></i><?php echo date('M d, Y', strtotime($transaction['created_at'])); ?></span>
                    </div>
                </div>
                <div class="txn-preview-amount">$<?php echo number_format($transaction['amount'],2); ?></div>
            </div>

            <!-- NOTICE -->
            <div class="notice">
                <i class="fas fa-triangle-exclamation notice-icon"></i>
                <div class="notice-text">
                    <strong>Before you proceed:</strong>
                    <ul>
                        <li>Try to resolve the issue directly with the other party first</li>
                        <li>Disputes should only be raised for serious, unresolvable issues</li>
                        <li>False or malicious disputes may result in account penalties</li>
                        <li>Provide as much detail and evidence as possible</li>
                    </ul>
                </div>
            </div>

            <?php if($error): ?>
                <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">

                <!-- REASON -->
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-header-icon"><i class="fas fa-list-check"></i></div>
                        <div class="panel-header-title">Select Dispute Reason</div>
                    </div>
                    <div class="panel-body">
                        <div class="reason-grid">
                            <?php
                            $reasons = [
                                'item_not_received'        => ['Item Not Received',          "You haven't received the item you purchased"],
                                'item_not_as_described'    => ['Item Not as Described',      "The item doesn't match the description or images"],
                                'payment_issue'            => ['Payment Issue',               'Problems with payment processing or refunds'],
                                'other_party_unresponsive' => ['Other Party Unresponsive',   'The other party is not responding to messages'],
                                'other'                    => ['Other Issue',                 'Any other issue not covered above'],
                            ];
                            foreach($reasons as $val => [$title, $desc]):
                            ?>
                            <label class="reason-opt">
                                <input type="radio" name="reason" value="<?php echo $val; ?>" required <?php echo ($_POST['reason']??'')===$val?'checked':''; ?>>
                                <div class="reason-box">
                                    <div class="reason-radio"></div>
                                    <div>
                                        <div class="reason-title"><?php echo $title; ?></div>
                                        <div class="reason-desc"><?php echo $desc; ?></div>
                                    </div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- DESCRIPTION -->
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-header-icon"><i class="fas fa-pen"></i></div>
                        <div class="panel-header-title">Detailed Description</div>
                    </div>
                    <div class="panel-body">
                        <label class="form-label">Describe the issue <span>*</span></label>
                        <textarea name="description" class="form-textarea" required
                            placeholder="Please describe what happened, when it happened, and what resolution you expect..."><?php echo htmlspecialchars($_POST['description']??''); ?></textarea>
                    </div>
                </div>

                <!-- EVIDENCE -->
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-header-icon"><i class="fas fa-paperclip"></i></div>
                        <div class="panel-header-title">Supporting Evidence <span style="font-size:0.78rem;color:var(--text-dim);font-family:var(--font-body);font-weight:400;">(Optional)</span></div>
                    </div>
                    <div class="panel-body">
                        <div class="file-zone" id="fileZone">
                            <i class="fas fa-cloud-arrow-up"></i>
                            <p>Click to upload or drag &amp; drop</p>
                            <small>Screenshots, chat logs, documents — max 5 MB</small>
                            <input type="file" name="evidence" id="fileInput" accept="image/*,.pdf,.doc,.docx">
                        </div>
                    </div>
                </div>

                <!-- ACTIONS -->
                <div class="action-row">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-scale-balanced"></i> Submit Dispute</button>
                    <a href="raise-dispute.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Back to Dispute Center</a>
                </div>

            </form>
        </div>
    </main>
</div>

<script>
    const hamburger = document.getElementById('hamburger');
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('overlay');
    function openSidebar()  { sidebar.classList.add('open');    overlay.classList.add('visible'); }
    function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('visible'); }
    hamburger.addEventListener('click', () => sidebar.classList.contains('open') ? closeSidebar() : openSidebar());
    overlay.addEventListener('click', closeSidebar);

    const fileZone  = document.getElementById('fileZone');
    const fileInput = document.getElementById('fileInput');
    fileZone.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', function() {
        const name = this.files[0]?.name;
        if(name) {
            fileZone.querySelector('p').textContent = 'Selected: ' + name;
            fileZone.querySelector('small').textContent = 'Click to change file';
            fileZone.querySelector('i').style.color = 'var(--teal)';
        }
    });
    fileZone.addEventListener('dragover',  e => { e.preventDefault(); fileZone.style.borderColor = 'var(--accent)'; });
    fileZone.addEventListener('dragleave', e => { e.preventDefault(); fileZone.style.borderColor = ''; });
    fileZone.addEventListener('drop', e => {
        e.preventDefault(); fileZone.style.borderColor = '';
        const files = e.dataTransfer.files;
        if(files.length) {
            fileInput.files = files;
            fileZone.querySelector('p').textContent = 'Selected: ' + files[0].name;
            fileZone.querySelector('small').textContent = 'Click to change file';
            fileZone.querySelector('i').style.color = 'var(--teal)';
        }
    });
</script>
</body>
</html>