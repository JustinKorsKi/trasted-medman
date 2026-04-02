<?php
require_once 'includes/config.php';

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if(!$product_id) { header('Location: products.php'); exit(); }

$query = "SELECT p.*, u.username as seller_name, u.id as seller_id, u.email as seller_email,
          u.phone as seller_phone, u.rating as seller_rating, u.midman_rating, u.total_midman_ratings
          FROM products p
          JOIN users u ON p.seller_id = u.id
          WHERE p.id = $product_id";

$result = mysqli_query($conn, $query);
if(mysqli_num_rows($result) == 0) { header('Location: products.php'); exit(); }
$product = mysqli_fetch_assoc($result);

$is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $product['seller_id'];
$other_products = mysqli_query($conn, "SELECT id, title, price, image_path
                                       FROM products
                                       WHERE seller_id = {$product['seller_id']}
                                       AND id != $product_id AND status = 'available'
                                       LIMIT 4");

$item_types = [
    'account'  => 'Game Account',
    'currency' => 'In-game Currency',
    'item'     => 'Item / Skin',
    'service'  => 'Boosting Service',
];
$item_icons = [
    'account'  => 'fa-gamepad',
    'currency' => 'fa-coins',
    'item'     => 'fa-box-open',
    'service'  => 'fa-bolt',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['title']); ?> — Trusted Midman</title>
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
        .avatar { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,var(--gold),#c47d00); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.85rem; color:#0f0c08; flex-shrink:0; box-shadow:0 0 10px rgba(240,165,0,0.2); }
        .user-pill-name { font-size:0.875rem; font-weight:500; color:var(--text-warm); }
        .user-pill-role { font-size:0.68rem; color:var(--gold); text-transform:uppercase; letter-spacing:0.09em; }

        /* ── MAIN ── */
        .main { margin-left:var(--sidebar-w); flex:1; min-height:100vh; display:flex; flex-direction:column; }
        .topbar { position:sticky; top:0; z-index:50; background:rgba(15,12,8,0.88); backdrop-filter:blur(24px); border-bottom:1px solid var(--border); padding:0 32px; height:64px; display:flex; align-items:center; justify-content:space-between; }
        .topbar-left { display:flex; align-items:center; gap:16px; }
        .hamburger { display:none; background:none; border:none; color:var(--text-muted); font-size:1.1rem; cursor:pointer; padding:6px; border-radius:7px; transition:color 0.2s; }
        .hamburger:hover { color:var(--text-warm); }
        .page-title { font-family:var(--font-head); font-size:1.15rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .online-dot { display:flex; align-items:center; gap:7px; font-size:0.78rem; color:var(--text-muted); }
        .online-dot::before { content:''; width:7px; height:7px; border-radius:50%; background:var(--teal); box-shadow:0 0 8px var(--teal); }
        .content { padding:28px 32px; flex:1; }

        /* ── BREADCRUMB ── */
        .breadcrumb { display:flex; align-items:center; gap:8px; font-size:0.78rem; color:var(--text-dim); margin-bottom:24px; }
        .breadcrumb a { color:var(--text-muted); text-decoration:none; transition:color 0.2s; }
        .breadcrumb a:hover { color:var(--gold); }
        .breadcrumb i { font-size:0.6rem; }
        .breadcrumb span { color:var(--text-muted); }

        /* ── PRODUCT MAIN GRID ── */
        .product-main { display:grid; grid-template-columns:1fr 1.1fr; gap:28px; margin-bottom:28px; align-items:start; }

        /* ── GALLERY ── */
        .gallery { position:sticky; top:80px; }
        .main-image {
            width:100%; height:380px;
            background:var(--surface); border:1px solid var(--border2);
            border-radius:var(--radius-lg);
            display:flex; align-items:center; justify-content:center;
            font-size:4rem; color:var(--text-dim);
            overflow:hidden; position:relative; margin-bottom:14px;
        }
        .main-image img { width:100%; height:100%; object-fit:cover; }

        .image-overlay-badges { position:absolute; top:14px; left:14px; display:flex; flex-direction:column; gap:6px; }
        .img-badge { display:inline-flex; align-items:center; gap:6px; font-size:0.68rem; font-weight:700; padding:4px 10px; border-radius:20px; backdrop-filter:blur(10px); letter-spacing:0.05em; }
        .img-badge-game { background:rgba(15,12,8,0.8); border:1px solid rgba(240,165,0,0.35); color:var(--gold); }
        .img-badge-type { background:rgba(15,12,8,0.8); border:1px solid rgba(0,212,170,0.3);  color:var(--teal); }

        .status-pill { position:absolute; top:14px; right:14px; display:flex; align-items:center; gap:6px; background:rgba(0,212,170,0.12); border:1px solid rgba(0,212,170,0.3); color:var(--teal); font-size:0.68rem; font-weight:700; padding:5px 11px; border-radius:20px; backdrop-filter:blur(10px); }
        .status-dot { width:6px; height:6px; border-radius:50%; background:var(--teal); box-shadow:0 0 6px var(--teal); }

        /* ── SPECS STRIP ── */
        .specs-strip { display:grid; grid-template-columns:repeat(auto-fill,minmax(100px,1fr)); gap:10px; }
        .spec-chip { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-sm); padding:10px 13px; transition:border-color 0.2s; }
        .spec-chip:hover { border-color:var(--border2); }
        .spec-chip-label { font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); margin-bottom:4px; }
        .spec-chip-value { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text-warm); }

        /* ── INFO COLUMN ── */
        .product-info { display:flex; flex-direction:column; gap:16px; }

        /* product header */
        .product-hd {
            background:var(--surface); border:1px solid var(--border2);
            border-radius:var(--radius); padding:24px;
            position:relative; overflow:hidden;
        }
        .product-hd::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.3),transparent); }
        .product-hd::after  { content:''; position:absolute; top:-60px; right:-60px; width:200px; height:200px; background:radial-gradient(circle,rgba(240,130,0,0.13) 0%,transparent 65%); pointer-events:none; }

        .product-title { font-family:var(--font-head); font-size:1.6rem; font-weight:800; color:var(--text); line-height:1.15; margin-bottom:14px; position:relative; z-index:1; letter-spacing:-0.01em; }
        .product-price { font-family:var(--font-head); font-size:2.6rem; font-weight:800; color:var(--gold); line-height:1; margin-bottom:16px; position:relative; z-index:1; letter-spacing:-0.02em; }
        .product-meta-row { display:flex; flex-wrap:wrap; gap:14px; font-size:0.78rem; color:var(--text-dim); position:relative; z-index:1; }
        .meta-pill { display:flex; align-items:center; gap:5px; }
        .meta-pill i { font-size:0.7rem; color:var(--gold); }

        /* seller card */
        .seller-card { background:var(--surface); border:1px solid var(--border2); border-radius:var(--radius); padding:20px; position:relative; overflow:hidden; }
        .seller-card::before { content:''; position:absolute; top:0; left:0; right:0; height:2px; background:linear-gradient(90deg,var(--gold),rgba(240,165,0,0)); }

        .seller-top { display:flex; align-items:center; gap:14px; margin-bottom:16px; padding-bottom:16px; border-bottom:1px solid var(--border); }
        .seller-ava { width:52px; height:52px; border-radius:50%; background:linear-gradient(135deg,var(--gold),#c48600); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:800; font-size:1.1rem; color:#0f0c08; flex-shrink:0; box-shadow:0 0 0 3px rgba(240,165,0,0.18); }
        .seller-name-line { font-family:var(--font-head); font-size:1.05rem; font-weight:700; color:var(--text); margin-bottom:4px; letter-spacing:-0.01em; }
        .seller-stars { display:flex; align-items:center; gap:3px; }
        .seller-stars i { font-size:0.75rem; color:var(--gold); }
        .seller-stars span { font-size:0.78rem; color:var(--text-muted); margin-left:5px; }

        .seller-stats { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .seller-stat { background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); padding:10px 12px; }
        .sstat-val { font-family:var(--font-head); font-size:1rem; font-weight:700; color:var(--text-warm); }
        .sstat-lbl { font-size:0.72rem; color:var(--text-dim); margin-top:1px; }

        .midman-row { margin-top:14px; padding-top:14px; border-top:1px solid var(--border); }
        .midman-lbl { font-size:0.68rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-dim); margin-bottom:6px; }
        .midman-stars { display:flex; align-items:center; gap:3px; }

        /* action box */
        .action-box { background:var(--surface); border:1px solid var(--border2); border-radius:var(--radius); padding:20px; }

        .btn { display:inline-flex; align-items:center; gap:8px; padding:12px 20px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.9rem; font-weight:700; text-decoration:none; cursor:pointer; border:none; transition:all 0.24s ease; white-space:nowrap; }
        .btn-buy { width:100%; justify-content:center; background:linear-gradient(135deg,var(--gold),#d48500); color:#0f0c08; font-size:1rem; padding:15px 20px; box-shadow:0 4px 24px var(--gold-glow), 0 1px 0 rgba(255,255,255,0.1) inset; margin-bottom:16px; }
        .btn-buy:hover { background:linear-gradient(135deg,var(--gold-lt),var(--gold)); transform:translateY(-2px); box-shadow:0 8px 32px rgba(240,165,0,0.4); }
        .btn-owner { flex:1; justify-content:center; }
        .btn-edit { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }
        .btn-edit:hover { color:var(--text-warm); border-color:var(--border3); }
        .btn-del  { background:var(--red-dim); color:var(--red); border:1px solid rgba(255,77,109,0.2); }
        .btn-del:hover { background:rgba(255,77,109,0.22); }
        .owner-row { display:flex; gap:10px; }

        .protection-list { display:flex; flex-direction:column; gap:10px; }
        .prot-item { display:flex; align-items:center; gap:10px; font-size:0.84rem; color:var(--text-muted); }
        .prot-item i { color:var(--teal); font-size:0.78rem; width:16px; text-align:center; }

        .guest-notice { background:var(--gold-dim); border:1px solid rgba(240,165,0,0.2); border-radius:var(--radius-sm); padding:14px 16px; font-size:0.875rem; color:var(--text-muted); display:flex; align-items:center; gap:10px; }
        .guest-notice i { color:var(--gold); }
        .guest-notice a { color:var(--gold); font-weight:600; text-decoration:none; }
        .guest-notice a:hover { text-decoration:underline; }

        /* ── PANELS ── */
        .panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; margin-bottom:20px; }
        .panel::before { content:''; display:block; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.15),transparent); }
        .panel-header { display:flex; align-items:center; gap:10px; padding:16px 22px; border-bottom:1px solid var(--border); }
        .panel-header-icon { width:28px; height:28px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; background:var(--gold-dim); color:var(--gold); border:1px solid rgba(240,165,0,0.14); }
        .panel-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .panel-body  { padding:20px 22px; }

        .desc-text { font-size:0.9rem; color:var(--text-muted); line-height:1.85; white-space:pre-line; }

        /* details table */
        .det-table { width:100%; border-collapse:collapse; }
        .det-table tr { border-bottom:1px solid var(--border); }
        .det-table tr:last-child { border-bottom:none; }
        .det-table td { padding:12px 16px; font-size:0.875rem; }
        .det-table td:first-child { color:var(--text-muted); font-weight:600; width:35%; }
        .det-table td:last-child  { color:var(--text-warm); }
        .det-table tbody tr:hover td { background:rgba(255,180,60,0.03); }

        /* ── MORE FROM SELLER ── */
        .more-section { margin-top:8px; }
        .more-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
        .more-title { font-family:var(--font-head); font-size:1.05rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:10px; letter-spacing:-0.01em; }
        .more-title-icon { width:28px; height:28px; border-radius:7px; background:var(--gold-dim); color:var(--gold); border:1px solid rgba(240,165,0,0.14); display:flex; align-items:center; justify-content:center; font-size:0.8rem; }
        .more-link { font-size:0.78rem; color:var(--gold); text-decoration:none; display:flex; align-items:center; gap:5px; transition:gap 0.2s; }
        .more-link:hover { gap:9px; }

        .more-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:14px; }
        .more-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; text-decoration:none; color:inherit; transition:all 0.25s ease; }
        .more-card:hover { border-color:rgba(240,165,0,0.25); transform:translateY(-4px); box-shadow:0 12px 30px rgba(0,0,0,0.4); }
        .more-thumb { width:100%; height:100px; background:var(--surface2); display:flex; align-items:center; justify-content:center; color:var(--text-dim); font-size:1.5rem; overflow:hidden; }
        .more-thumb img { width:100%; height:100%; object-fit:cover; transition:transform 0.3s; }
        .more-card:hover .more-thumb img { transform:scale(1.06); }
        .more-info { padding:10px 13px; }
        .more-name  { font-size:0.82rem; font-weight:600; color:var(--text-warm); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:4px; }
        .more-price { font-family:var(--font-head); font-size:0.95rem; font-weight:800; color:var(--gold); letter-spacing:-0.01em; }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:1100px) { :root { --sidebar-w:220px; } }
        @media(max-width:900px)  { .product-main { grid-template-columns:1fr; } .gallery { position:static; } }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
        @media(max-width:540px)  { .specs-strip{grid-template-columns:repeat(2,1fr);} .seller-stats{grid-template-columns:1fr 1fr;} }
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
            <?php $role = $_SESSION['role'] ?? 'guest'; ?>
            <?php if($role == 'seller'): ?>
                <div class="nav-label">Seller</div>
                <a href="seller-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="my-products.php"      class="nav-link active"><span class="nav-icon"><i class="fas fa-box-open"></i></span> My Products</a>
                <a href="add-product.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-plus-circle"></i></span> Add Product</a>
                 <a href="my-transactions.php" class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span>Transactions</a>
                <a href="my-sales.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-chart-line"></i></span> Sales</a>
                <a href="seller-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <?php elseif($role == 'midman'): ?>
                <div class="nav-label">Midman</div>
                <a href="midman-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-handshake"></i></span> Transactions</a>
                <a href="midman-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
            <?php else: ?>
                <div class="nav-label">Buyer</div>
                <?php if(isset($_SESSION['user_id'])): ?><a href="buyer-dashboard.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a><?php endif; ?>
                <a href="products.php"         class="nav-link active"><span class="nav-icon"><i class="fas fa-store"></i></span> Browse Products</a>
                <?php if(isset($_SESSION['user_id'])): ?><a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> My Purchases</a><?php endif; ?>
                <?php if(isset($_SESSION['user_id'])): ?><a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span>Dispute Center</a><?php endif; ?>
            <?php endif; ?>
            <div class="nav-label" style="margin-top:10px;">Account</div>
             <a href="apply-midman.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span>Apply as Midman</a>
            <?php if(isset($_SESSION['user_id'])): ?><a href="profile.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a><?php endif; ?>
            <?php if(isset($_SESSION['user_id'])): ?><a href="logout.php"  class="nav-link" style="color:var(--text-dim);margin-top:6px;"><span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out</a><?php endif; ?>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['username'] ?? 'GU', 0, 2)); ?></div>
                <div>
                    <div class="user-pill-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Guest'); ?></div>
                    <div class="user-pill-role"><?php echo ucfirst($_SESSION['role'] ?? 'Guest'); ?></div>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <span class="page-title">Product Details</span>
            </div>
            <div class="online-dot">Online</div>
        </header>

        <div class="content">

            <!-- BREADCRUMB -->
            <div class="breadcrumb">
    <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'seller' && $is_owner): ?>
        <a href="my-products.php">My Products</a>
    <?php else: ?>
        <a href="products.php">Browse Products</a>
    <?php endif; ?>
    <i class="fas fa-chevron-right"></i>
    <span><?php echo htmlspecialchars($product['title']); ?></span>
</div>
            <!-- MAIN GRID -->
            <div class="product-main">

                <!-- GALLERY -->
                <div class="gallery">
                    <div class="main-image">
                        <?php if($product['image_path']): ?>
                            <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>">
                        <?php else: ?>
                            <i class="fas fa-gamepad"></i>
                        <?php endif; ?>

                        <div class="image-overlay-badges">
                            <?php if(!empty($product['game_name'])): ?>
                                <span class="img-badge img-badge-game"><i class="fas fa-gamepad"></i> <?php echo htmlspecialchars($product['game_name']); ?></span>
                            <?php endif; ?>
                            <?php if(!empty($product['item_type']) && isset($item_types[$product['item_type']])): ?>
                                <span class="img-badge img-badge-type"><i class="fas <?php echo $item_icons[$product['item_type']] ?? 'fa-tag'; ?>"></i> <?php echo $item_types[$product['item_type']]; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="status-pill"><div class="status-dot"></div> Available</div>
                    </div>

                    <!-- SPECS STRIP -->
                    <?php if($product['game_name'] || $product['account_level'] || $product['account_rank'] || $product['server_region']): ?>
                    <div class="specs-strip">
                        <?php if(!empty($product['game_name'])): ?>
                            <div class="spec-chip"><div class="spec-chip-label">Game</div><div class="spec-chip-value"><?php echo htmlspecialchars($product['game_name']); ?></div></div>
                        <?php endif; ?>
                        <?php if(!empty($product['account_level'])): ?>
                            <div class="spec-chip"><div class="spec-chip-label">Level</div><div class="spec-chip-value"><?php echo htmlspecialchars($product['account_level']); ?></div></div>
                        <?php endif; ?>
                        <?php if(!empty($product['account_rank'])): ?>
                            <div class="spec-chip"><div class="spec-chip-label">Rank</div><div class="spec-chip-value"><?php echo htmlspecialchars($product['account_rank']); ?></div></div>
                        <?php endif; ?>
                        <?php if(!empty($product['server_region'])): ?>
                            <div class="spec-chip"><div class="spec-chip-label">Server</div><div class="spec-chip-value"><?php echo htmlspecialchars($product['server_region']); ?></div></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- INFO COLUMN -->
                <div class="product-info">

                    <!-- PRODUCT HEADER -->
                    <div class="product-hd">
                        <h1 class="product-title"><?php echo htmlspecialchars($product['title']); ?></h1>
                        <div class="product-price">$<?php echo number_format($product['price'], 2); ?></div>
                        <div class="product-meta-row">
                            <span class="meta-pill"><i class="fas fa-tag"></i> #<?php echo $product['id']; ?></span>
                            <span class="meta-pill"><i class="fas fa-calendar-days"></i> <?php echo date('M j, Y', strtotime($product['created_at'])); ?></span>
                            <?php if(!empty($product['category'])): ?>
                                <span class="meta-pill"><i class="fas fa-folder"></i> <?php echo htmlspecialchars($product['category']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- SELLER CARD -->
                    <div class="seller-card">
                        <div class="seller-top">
                            <div class="seller-ava"><?php echo strtoupper(substr($product['seller_name'], 0, 2)); ?></div>
                            <div>
                                <div class="seller-name-line"><?php echo htmlspecialchars($product['seller_name']); ?></div>
                                <div class="seller-stars">
                                    <?php $sr = round($product['seller_rating'] ?? 0);
                                    for($i=1;$i<=5;$i++): ?>
                                        <i class="fas fa-star" style="color:<?php echo $i<=$sr?'var(--gold)':'var(--text-dim)';?>"></i>
                                    <?php endfor; ?>
                                    <span><?php echo number_format($product['seller_rating'] ?? 0, 1); ?> seller rating</span>
                                </div>
                            </div>
                        </div>

                        <div class="seller-stats">
                            <div class="seller-stat">
                                <div class="sstat-val"><?php echo rand(10,100); ?></div>
                                <div class="sstat-lbl">Total Sales</div>
                            </div>
                            <div class="seller-stat">
                                <div class="sstat-val"><?php echo rand(1,12); ?>h</div>
                                <div class="sstat-lbl">Response Time</div>
                            </div>
                            <div class="seller-stat">
                                <div class="sstat-val"><?php echo date('M Y', strtotime($product['created_at'])); ?></div>
                                <div class="sstat-lbl">Member Since</div>
                            </div>
                            <?php if(isset($_SESSION['user_id']) && !$is_owner): ?>
                            <div class="seller-stat">
                                <a href="mailto:<?php echo htmlspecialchars($product['seller_email']); ?>" style="color:var(--gold);font-size:0.82rem;font-weight:600;text-decoration:none;">
                                    <i class="fas fa-envelope"></i> Contact
                                </a>
                                <div class="sstat-lbl">via Email</div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if($product['total_midman_ratings'] > 0): ?>
                        <div class="midman-row">
                            <div class="midman-lbl">Also rated as Midman</div>
                            <div class="midman-stars">
                                <?php $mr = round($product['midman_rating'] ?? 0);
                                for($i=1;$i<=5;$i++): ?>
                                    <i class="fas fa-star" style="font-size:0.72rem;color:<?php echo $i<=$mr?'var(--gold)':'var(--text-dim)';?>"></i>
                                <?php endfor; ?>
                                <span style="font-size:0.75rem;color:var(--text-muted);margin-left:5px;">
                                    <?php echo number_format($product['midman_rating'] ?? 0, 1); ?>/5
                                    (<?php echo $product['total_midman_ratings']; ?> reviews)
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- ACTION BOX -->
                    <div class="action-box">
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <?php if($is_owner): ?>
                                <div class="owner-row">
                                    <a href="edit-product.php?id=<?php echo $product['id']; ?>" class="btn btn-edit btn-owner"><i class="fas fa-pen"></i> Edit Product</a>
                                    <a href="delete-product.php?id=<?php echo $product['id']; ?>" class="btn btn-del btn-owner" onclick="return confirm('Delete this product?')"><i class="fas fa-trash"></i> Delete</a>
                                </div>
                            <?php elseif($_SESSION['role'] == 'buyer'): ?>
                                <a href="buy-product.php?id=<?php echo $product['id']; ?>" class="btn btn-buy">
                                    <i class="fas fa-shield-halved"></i> Buy Now — Midman Protected
                                </a>
                                <div class="protection-list">
                                    <div class="prot-item"><i class="fas fa-lock"></i> Payment held securely in escrow</div>
                                    <div class="prot-item"><i class="fas fa-user-check"></i> Full protection for buyer &amp; seller</div>
                                    <div class="prot-item"><i class="fas fa-scale-balanced"></i> Dispute resolution if anything goes wrong</div>
                                    <div class="prot-item"><i class="fas fa-circle-check"></i> Release payment only when you confirm</div>
                                </div>
                            <?php else: ?>
                                <div class="guest-notice"><i class="fas fa-circle-info"></i> Only buyers can purchase items.</div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="guest-notice">
                                <i class="fas fa-shield-halved"></i>
                                <span><a href="login.php">Sign in</a> or <a href="register.php">create an account</a> to purchase with Midman protection.</span>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <!-- DESCRIPTION -->
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-header-icon"><i class="fas fa-file-lines"></i></div>
                    <span class="panel-title">Description</span>
                </div>
                <div class="panel-body">
                    <div class="desc-text"><?php echo nl2br(htmlspecialchars($product['description'])); ?></div>
                </div>
            </div>

            <!-- DETAILS TABLE -->
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-header-icon"><i class="fas fa-circle-info"></i></div>
                    <span class="panel-title">Additional Details</span>
                </div>
                <div class="panel-body" style="padding:0;">
                    <table class="det-table">
                        <?php if(!empty($product['category'])): ?>
                        <tr><td>Category</td><td><?php echo htmlspecialchars($product['category']); ?></td></tr>
                        <?php endif; ?>
                        <?php if(!empty($product['condition'])): ?>
                        <tr><td>Condition</td><td><?php echo ucfirst($product['condition']); ?></td></tr>
                        <?php endif; ?>
                        <tr><td>Listed Date</td><td><?php echo date('F j, Y · g:i A', strtotime($product['created_at'])); ?></td></tr>
                        <tr><td>Item ID</td><td>#<?php echo $product['id']; ?></td></tr>
                        <tr><td>Status</td><td style="color:var(--teal);font-weight:700;"><i class="fas fa-circle-check" style="font-size:0.75rem;margin-right:4px;"></i> Available</td></tr>
                    </table>
                </div>
            </div>

            <!-- MORE FROM SELLER -->
            <?php if(mysqli_num_rows($other_products) > 0): ?>
            <div class="more-section">
                <div class="more-head">
                    <div class="more-title">
                        <div class="more-title-icon"><i class="fas fa-store"></i></div>
                        More from <?php echo htmlspecialchars($product['seller_name']); ?>
                    </div>
                    <a href="my-products.php" class="more-link">See all <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="more-grid">
                    <?php while($other = mysqli_fetch_assoc($other_products)): ?>
                    <a href="product-detail.php?id=<?php echo $other['id']; ?>" class="more-card">
                        <div class="more-thumb">
                            <?php if($other['image_path']): ?>
                                <img src="<?php echo htmlspecialchars($other['image_path']); ?>" alt="">
                            <?php else: ?>
                                <i class="fas fa-gamepad"></i>
                            <?php endif; ?>
                        </div>
                        <div class="more-info">
                            <div class="more-name"><?php echo htmlspecialchars($other['title']); ?></div>
                            <div class="more-price">$<?php echo number_format($other['price'], 2); ?></div>
                        </div>
                    </a>
                    <?php endwhile; ?>
                </div>
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
    overlay.addEventListener('click',   () => { sidebar.classList.remove('open');  overlay.classList.remove('visible'); });
</script>
</body>
</html>