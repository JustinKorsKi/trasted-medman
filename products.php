<?php
require_once 'includes/config.php';

$search           = isset($_GET['search'])    ? mysqli_real_escape_string($conn, $_GET['search'])    : '';
$game_filter      = isset($_GET['game'])      ? mysqli_real_escape_string($conn, $_GET['game'])      : '';
$item_type_filter = isset($_GET['item_type']) ? mysqli_real_escape_string($conn, $_GET['item_type']) : '';
$min_price        = (isset($_GET['min_price']) && $_GET['min_price'] !== '') ? floatval($_GET['min_price']) : '';
$max_price        = (isset($_GET['max_price']) && $_GET['max_price'] !== '') ? floatval($_GET['max_price']) : '';
$sort             = isset($_GET['sort'])      ? $_GET['sort'] : 'newest';
$my_products      = isset($_GET['my']) && $_GET['my'] == 1 && isset($_SESSION['user_id']) && $_SESSION['role'] == 'seller';

$query = "SELECT p.*, u.username as seller_name, u.id as seller_id,
          COALESCE(u.rating, 0) as seller_rating
          FROM products p
          JOIN users u ON p.seller_id = u.id
          WHERE p.status = 'available'";

if ($my_products)      $query .= " AND p.seller_id = " . $_SESSION['user_id'];
if ($search)           $query .= " AND (p.title LIKE '%$search%' OR p.description LIKE '%$search%')";
if ($game_filter)      $query .= " AND p.game_name = '$game_filter'";
if ($item_type_filter) $query .= " AND p.item_type = '$item_type_filter'";
if ($min_price !== '') $query .= " AND p.price >= $min_price";
if ($max_price !== '') $query .= " AND p.price <= $max_price";

switch ($sort) {
    case 'price_low':  $query .= " ORDER BY p.price ASC";      break;
    case 'price_high': $query .= " ORDER BY p.price DESC";     break;
    case 'oldest':     $query .= " ORDER BY p.created_at ASC"; break;
    default:           $query .= " ORDER BY p.created_at DESC";
}

$result     = mysqli_query($conn, $query);
$totalCount = mysqli_num_rows($result);
$games      = mysqli_query($conn, "SELECT DISTINCT game_name FROM products WHERE game_name IS NOT NULL AND game_name != '' ORDER BY game_name");

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
    <title><?php echo $my_products ? 'My Products' : 'Browse Products'; ?> — Trusted Midman</title>
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
        .nav-section-label { font-size:0.65rem; font-weight:700; letter-spacing:0.14em; text-transform:uppercase; color:var(--text-dim); padding:12px 12px 7px; }
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
        .topbar-right { display:flex; align-items:center; gap:10px; }
        .content { padding:28px 32px; flex:1; }

        /* ── ANIMATIONS ── */
        @keyframes fadeUp  { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
        @keyframes fadeIn  { from{opacity:0;transform:translateX(-8px)} to{opacity:1;transform:translateX(0)} }

        /* ── BUTTONS ── */
        .btn { display:inline-flex; align-items:center; gap:8px; padding:9px 18px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.875rem; font-weight:600; text-decoration:none; cursor:pointer; border:none; transition:all 0.22s ease; white-space:nowrap; letter-spacing:0.01em; }
        .btn-primary { background:linear-gradient(135deg,var(--gold),#d48500); color:#0f0c08; font-weight:700; box-shadow:0 3px 14px var(--gold-glow); }
        .btn-primary:hover { background:linear-gradient(135deg,var(--gold-lt),var(--gold)); transform:translateY(-2px); box-shadow:0 6px 20px var(--gold-glow); }
        .btn-ghost { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }
        .btn-ghost:hover { color:var(--text-warm); border-color:var(--border3); transform:translateY(-1px); }
        .btn-danger { background:var(--red-dim); color:var(--red); border:1px solid rgba(255,77,109,0.2); }
        .btn-danger:hover { background:rgba(255,77,109,0.22); }
        .btn-sm { padding:6px 13px; font-size:0.8rem; }

        /* ── PAGE HEAD ── */
        .page-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .page-head-left h1 { font-family:var(--font-head); font-size:1.8rem; font-weight:800; color:var(--text); display:flex; align-items:center; gap:12px; line-height:1; letter-spacing:-0.01em; }
        .page-head-icon { width:42px; height:42px; background:var(--gold-dim); border:1px solid rgba(240,165,0,0.14); border-radius:12px; display:flex; align-items:center; justify-content:center; color:var(--gold); font-size:1.1rem; }
        .page-head-sub { font-size:0.84rem; color:var(--text-muted); margin-top:6px; }
        .count-chip { background:var(--gold-dim); color:var(--gold); font-size:0.72rem; font-weight:700; padding:3px 10px; border-radius:20px; letter-spacing:0.04em; border:1px solid rgba(240,165,0,0.15); }

        /* ── VIEW TOGGLE ── */
        .view-toggle { display:flex; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); overflow:hidden; }
        .view-btn { padding:8px 14px; background:transparent; border:none; color:var(--text-muted); cursor:pointer; transition:all 0.2s; font-size:0.85rem; }
        .view-btn.active { background:linear-gradient(135deg,var(--gold),#d48500); color:#0f0c08; }
        .view-btn:hover:not(.active) { color:var(--text-warm); }

        /* ── ACTIVE FILTER CHIPS ── */
        .active-filters { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px; }
        .filter-chip { display:inline-flex; align-items:center; gap:6px; background:var(--gold-dim); color:var(--gold); border:1px solid rgba(240,165,0,0.2); border-radius:20px; padding:4px 11px; font-size:0.75rem; font-weight:600; text-decoration:none; transition:background 0.2s; }
        .filter-chip:hover { background:rgba(240,165,0,0.22); }

        /* ── FILTER PANEL ── */
        .filter-panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); margin-bottom:24px; overflow:hidden; }
        .filter-panel::before { content:''; display:block; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.18),transparent); }
        .filter-panel-header { display:flex; align-items:center; justify-content:space-between; padding:15px 22px; cursor:pointer; border-bottom:1px solid var(--border); transition:background 0.2s; }
        .filter-panel-header:hover { background:var(--surface2); }
        .filter-panel-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); display:flex; align-items:center; gap:10px; letter-spacing:-0.01em; }
        .filter-panel-icon { width:26px; height:26px; border-radius:7px; background:var(--gold-dim); color:var(--gold); border:1px solid rgba(240,165,0,0.14); display:flex; align-items:center; justify-content:center; font-size:0.75rem; }
        .filter-chevron { color:var(--text-muted); font-size:0.8rem; transition:transform 0.3s; }
        .filter-chevron.collapsed { transform:rotate(180deg); }
        .filter-body { padding:20px 22px; }

        .filter-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:14px; margin-bottom:18px; }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        .form-label { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); }
        .form-control { padding:10px 13px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); color:var(--text-warm); font-family:var(--font-body); font-size:0.875rem; transition:all 0.2s; width:100%; outline:none; }
        .form-control:focus { border-color:var(--gold); box-shadow:0 0 0 3px rgba(240,165,0,0.1); background:var(--surface3); }
        .form-control::placeholder { color:var(--text-dim); }
        select.form-control option { background:#201a13; }
        .filter-actions { display:flex; gap:10px; flex-wrap:wrap; }

        /* ── PRODUCT GRID ── */
        .products-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(270px,1fr)); gap:18px; }

        .product-card {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden;
            display:flex; flex-direction:column;
            transition:all 0.25s ease;
            opacity:0; transform:translateY(14px);
            animation:fadeUp 0.45s ease forwards;
        }
        .product-card:nth-child(1)   { animation-delay:0.03s; }
        .product-card:nth-child(2)   { animation-delay:0.07s; }
        .product-card:nth-child(3)   { animation-delay:0.11s; }
        .product-card:nth-child(4)   { animation-delay:0.15s; }
        .product-card:nth-child(5)   { animation-delay:0.19s; }
        .product-card:nth-child(6)   { animation-delay:0.23s; }
        .product-card:nth-child(n+7) { animation-delay:0.27s; }

        .product-card:hover { border-color:rgba(240,165,0,0.22); transform:translateY(-5px); box-shadow:0 16px 40px rgba(0,0,0,0.45); }

        .product-thumb { width:100%; height:180px; background:var(--surface2); display:flex; align-items:center; justify-content:center; color:var(--text-dim); font-size:2.5rem; overflow:hidden; position:relative; flex-shrink:0; }
        .product-thumb img { width:100%; height:100%; object-fit:cover; transition:transform 0.35s; }
        .product-card:hover .product-thumb img { transform:scale(1.05); }

        .thumb-badge { position:absolute; top:10px; right:10px; background:var(--gold); color:#0f0c08; font-size:0.65rem; font-weight:800; padding:3px 9px; border-radius:20px; letter-spacing:0.05em; text-transform:uppercase; }
        .type-pip    { position:absolute; top:10px; left:10px; background:rgba(15,12,8,0.8); backdrop-filter:blur(8px); color:var(--teal); border:1px solid rgba(0,212,170,0.25); font-size:0.65rem; font-weight:700; padding:3px 8px; border-radius:20px; letter-spacing:0.04em; }

        .product-body { padding:16px 18px; flex:1; display:flex; flex-direction:column; gap:10px; }
        .product-name { font-family:var(--font-head); font-size:1rem; font-weight:700; color:var(--text); line-height:1.3; letter-spacing:-0.01em; }

        .product-tags { display:flex; flex-wrap:wrap; gap:6px; }
        .tag { display:inline-flex; align-items:center; gap:4px; font-size:0.7rem; font-weight:600; padding:3px 8px; border-radius:6px; }
        .tag-game { background:var(--gold-dim); color:var(--gold); border:1px solid rgba(240,165,0,0.14); }
        .tag-spec { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border); }

        .product-price { font-family:var(--font-head); font-size:1.5rem; font-weight:800; color:var(--gold); letter-spacing:-0.01em; }

        .product-seller { display:flex; align-items:center; gap:8px; padding-top:8px; border-top:1px solid var(--border); }
        .seller-ava { width:26px; height:26px; border-radius:50%; background:linear-gradient(135deg,var(--gold),#c47d00); display:flex; align-items:center; justify-content:center; font-size:0.6rem; font-weight:800; color:#0f0c08; flex-shrink:0; }
        .seller-name   { font-size:0.8rem; color:var(--text-muted); flex:1; }
        .seller-rating { font-size:0.75rem; color:var(--gold); font-weight:600; }

        .product-foot { display:flex; gap:8px; padding:12px 18px; border-top:1px solid var(--border); }
        .product-foot .btn { flex:1; justify-content:center; font-size:0.8rem; padding:8px 10px; }

        /* ── LIST VIEW ── */
        .products-list { display:none; flex-direction:column; gap:12px; }

        .list-item {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius);
            display:grid; grid-template-columns:110px 1fr auto;
            gap:18px; align-items:center; padding:16px 20px;
            transition:all 0.22s ease;
            opacity:0; transform:translateX(-8px);
            animation:fadeIn 0.4s ease forwards;
        }
        .list-item:nth-child(1)   { animation-delay:0.04s; }
        .list-item:nth-child(2)   { animation-delay:0.09s; }
        .list-item:nth-child(3)   { animation-delay:0.14s; }
        .list-item:nth-child(n+4) { animation-delay:0.19s; }
        .list-item:hover { border-color:var(--border2); transform:translateX(4px); box-shadow:0 8px 24px rgba(0,0,0,0.3); }

        .list-thumb { width:110px; height:80px; border-radius:var(--radius-sm); background:var(--surface2); border:1px solid var(--border); display:flex; align-items:center; justify-content:center; color:var(--text-dim); font-size:1.5rem; overflow:hidden; flex-shrink:0; }
        .list-thumb img { width:100%; height:100%; object-fit:cover; }
        .list-info  { flex:1; min-width:0; }
        .list-name  { font-family:var(--font-head); font-size:1rem; font-weight:700; color:var(--text); margin-bottom:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; letter-spacing:-0.01em; }
        .list-meta  { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:6px; }
        .list-price { font-family:var(--font-head); font-size:1.3rem; font-weight:800; color:var(--gold); margin-bottom:4px; letter-spacing:-0.01em; }
        .list-seller { font-size:0.77rem; color:var(--text-muted); display:flex; align-items:center; gap:6px; }
        .list-actions { display:flex; flex-direction:column; gap:8px; flex-shrink:0; }
        .list-actions .btn { justify-content:center; }
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

        /* ── EMPTY ── */
        .empty { text-align:center; padding:64px 24px; color:var(--text-muted); }
        .empty-icon { width:80px; height:80px; background:var(--surface2); border:1px solid var(--border); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:2rem; color:var(--text-dim); margin:0 auto 20px; }
        .empty h3 { font-family:var(--font-head); font-size:1.3rem; color:var(--text); margin-bottom:8px; letter-spacing:-0.01em; }
        .empty p  { font-size:0.875rem; margin-bottom:22px; }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:1100px) { :root{--sidebar-w:220px;} .products-grid{grid-template-columns:repeat(auto-fill,minmax(230px,1fr));} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} .filter-grid{grid-template-columns:repeat(2,1fr);} .list-item{grid-template-columns:1fr;} .list-thumb{width:100%;height:120px;} .list-actions{flex-direction:row;} }
        @media(max-width:540px)  { .products-grid{grid-template-columns:1fr;} .filter-grid{grid-template-columns:1fr;} }
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
        <div class="nav-section-label">Buyer</div>
        <?php if(isset($_SESSION['user_id'])): ?><a href="buyer-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a> <?php endif; ?>
        <a href="products.php"        class="nav-link active"><span class="nav-icon"><i class="fas fa-store"></i></span> Browse Products</a>
         <?php if(isset($_SESSION['user_id'])): ?><a href="my-transactions.php" class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> My Purchases</a><?php endif; ?>
        <?php if(isset($_SESSION['user_id'])): ?><a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span>Dispute Center</a><?php endif; ?>
        <div class="nav-section-label" style="margin-top:10px;">Account</div>
        <a href="apply-midman.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span>Apply as Midman</a>
        <?php if(isset($_SESSION['user_id'])): ?>
        <a href="profile.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a>
        <a href="logout.php"  class="nav-link" style="color:var(--text-dim);margin-top:6px;"><span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out</a>
    <?php endif; ?>
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
                <span class="page-title"><?php echo $my_products ? 'My Products' : 'Browse Products'; ?></span>
            </div>
            <div class="topbar-right">
                <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] == 'seller'): ?>
                    <a href="add-product.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Product</a>
                <?php endif; ?>
            </div>
        </header>

        <div class="content">

            <!-- PAGE HEAD -->
            <div class="page-head">
                <div class="page-head-left">
                    <h1>
                        <div class="page-head-icon"><i class="fas fa-store"></i></div>
                        <?php echo $my_products ? 'My Products' : 'Browse Products'; ?>
                        <span class="count-chip"><?php echo $totalCount; ?> listings</span>
                    </h1>
                    <div class="page-head-sub">
                        <?php echo $my_products ? 'Manage your active listings' : 'Discover gaming accounts, items, and services'; ?>
                    </div>
                </div>
                <div class="view-toggle">
                    <button class="view-btn active" id="gridViewBtn" title="Grid view"><i class="fas fa-grip"></i></button>
                    <button class="view-btn"        id="listViewBtn" title="List view"><i class="fas fa-list"></i></button>
                </div>
            </div>

            <!-- ACTIVE FILTER CHIPS -->
            <?php $hasFilters = $search || $game_filter || $item_type_filter || $min_price !== '' || $max_price !== '';
            if($hasFilters): ?>
            <div class="active-filters">
                <?php if($search): ?>
                    <a href="?<?php echo http_build_query(array_filter(array_merge($_GET,['search'=>'']))); ?>" class="filter-chip">
                        <i class="fas fa-search"></i> "<?php echo htmlspecialchars($search); ?>" <i class="fas fa-xmark"></i>
                    </a>
                <?php endif; ?>
                <?php if($game_filter): ?>
                    <a href="?<?php echo http_build_query(array_filter(array_merge($_GET,['game'=>'']))); ?>" class="filter-chip">
                        <i class="fas fa-gamepad"></i> <?php echo htmlspecialchars($game_filter); ?> <i class="fas fa-xmark"></i>
                    </a>
                <?php endif; ?>
                <?php if($item_type_filter && isset($item_types[$item_type_filter])): ?>
                    <a href="?<?php echo http_build_query(array_filter(array_merge($_GET,['item_type'=>'']))); ?>" class="filter-chip">
                        <i class="fas fa-tag"></i> <?php echo $item_types[$item_type_filter]; ?> <i class="fas fa-xmark"></i>
                    </a>
                <?php endif; ?>
                <?php if($min_price !== '' || $max_price !== ''): ?>
                    <a href="?<?php echo http_build_query(array_filter(array_merge($_GET,['min_price'=>'','max_price'=>'']))); ?>" class="filter-chip">
                        <i class="fas fa-dollar-sign"></i>
                        <?php echo $min_price !== '' ? '$'.number_format($min_price,2) : ''; ?>
                        <?php echo ($min_price !== '' && $max_price !== '') ? ' – ' : ''; ?>
                        <?php echo $max_price !== '' ? '$'.number_format($max_price,2) : ''; ?>
                        <i class="fas fa-xmark"></i>
                    </a>
                <?php endif; ?>
                <a href="products.php<?php echo $my_products ? '?my=1' : ''; ?>" class="filter-chip" style="background:var(--red-dim);color:var(--red);border-color:rgba(255,77,109,0.2);">
                    <i class="fas fa-rotate-left"></i> Clear all
                </a>
            </div>
            <?php endif; ?>

            <!-- FILTER PANEL -->
            <div class="filter-panel">
                <div class="filter-panel-header" id="filterToggle">
                    <div class="filter-panel-title">
                        <div class="filter-panel-icon"><i class="fas fa-sliders"></i></div>
                        Filters &amp; Sort
                    </div>
                    <i class="fas fa-chevron-up filter-chevron" id="filterChevron"></i>
                </div>
                <div class="filter-body" id="filterBody">
                    <form method="GET" action="">
                        <?php if($my_products): ?><input type="hidden" name="my" value="1"><?php endif; ?>
                        <div class="filter-grid">
                            <div class="form-group">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Keywords…">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Game</label>
                                <select name="game" class="form-control">
                                    <option value="">All Games</option>
                                    <?php if($games && mysqli_num_rows($games) > 0): while($g = mysqli_fetch_assoc($games)): ?>
                                        <option value="<?php echo $g['game_name']; ?>" <?php echo $game_filter == $g['game_name'] ? 'selected' : ''; ?>><?php echo $g['game_name']; ?></option>
                                    <?php endwhile; endif; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Item Type</label>
                                <select name="item_type" class="form-control">
                                    <option value="">All Types</option>
                                    <?php foreach($item_types as $k => $v): ?>
                                        <option value="<?php echo $k; ?>" <?php echo $item_type_filter==$k?'selected':''; ?>><?php echo $v; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Min Price</label>
                                <input type="number" name="min_price" value="<?php echo $min_price; ?>" step="0.01" class="form-control" placeholder="$0.00">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Max Price</label>
                                <input type="number" name="max_price" value="<?php echo $max_price; ?>" step="0.01" class="form-control" placeholder="$1000">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Sort By</label>
                                <select name="sort" class="form-control">
                                    <option value="newest"     <?php echo $sort=='newest'     ?'selected':''; ?>>Newest First</option>
                                    <option value="oldest"     <?php echo $sort=='oldest'     ?'selected':''; ?>>Oldest First</option>
                                    <option value="price_low"  <?php echo $sort=='price_low'  ?'selected':''; ?>>Price: Low → High</option>
                                    <option value="price_high" <?php echo $sort=='price_high' ?'selected':''; ?>>Price: High → Low</option>
                                </select>
                            </div>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass"></i> Apply Filters</button>
                            <a href="products.php<?php echo $my_products?'?my=1':''; ?>" class="btn btn-ghost"><i class="fas fa-rotate-left"></i> Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- PRODUCTS -->
            <?php if($totalCount > 0): ?>

            <!-- GRID VIEW -->
            <div class="products-grid" id="productsGrid">
                <?php while($p = mysqli_fetch_assoc($result)): ?>
                <div class="product-card">
                    <div class="product-thumb">
                        <?php if($p['image_path']): ?>
                            <img src="<?php echo htmlspecialchars($p['image_path']); ?>" alt="<?php echo htmlspecialchars($p['title']); ?>">
                        <?php else: ?>
                            <i class="fas fa-gamepad"></i>
                        <?php endif; ?>
                        <?php if(!empty($p['item_type']) && isset($item_types[$p['item_type']])): ?>
                            <div class="type-pip"><i class="fas <?php echo $item_icons[$p['item_type']]??'fa-tag'; ?>"></i> <?php echo $item_types[$p['item_type']]; ?></div>
                        <?php endif; ?>
                        <?php if($my_products): ?><div class="thumb-badge">Mine</div><?php endif; ?>
                    </div>
                    <div class="product-body">
                        <div class="product-name"><?php echo htmlspecialchars($p['title']); ?></div>
                        <div class="product-tags">
                            <?php if(!empty($p['game_name'])): ?><span class="tag tag-game"><i class="fas fa-gamepad"></i> <?php echo htmlspecialchars($p['game_name']); ?></span><?php endif; ?>
                            <?php if(!empty($p['account_level'])): ?><span class="tag tag-spec">Lvl <?php echo $p['account_level']; ?></span><?php endif; ?>
                            <?php if(!empty($p['account_rank'])): ?><span class="tag tag-spec"><?php echo htmlspecialchars($p['account_rank']); ?></span><?php endif; ?>
                            <?php if(!empty($p['server_region'])): ?><span class="tag tag-spec"><?php echo htmlspecialchars($p['server_region']); ?></span><?php endif; ?>
                        </div>
                        <div class="product-price">$<?php echo number_format($p['price'],2); ?></div>
                        <div class="product-seller">
                            <div class="seller-ava"><?php echo strtoupper(substr($p['seller_name'],0,2)); ?></div>
                            <span class="seller-name"><?php echo htmlspecialchars($p['seller_name']); ?></span>
                            <?php if($p['seller_rating'] > 0): ?>
                                <span class="seller-rating"><i class="fas fa-star"></i> <?php echo number_format($p['seller_rating'],1); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="product-foot">
                        <a href="product-detail.php?id=<?php echo $p['id']; ?>" class="btn btn-primary"><i class="fas fa-eye"></i> View</a>
                        <?php if($my_products): ?>
                            <a href="edit-product.php?id=<?php echo $p['id']; ?>" class="btn btn-ghost"><i class="fas fa-pen"></i></a>
                            <a href="delete-product.php?id=<?php echo $p['id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this product?')"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <!-- LIST VIEW -->
            <div class="products-list" id="productsList">
                <?php mysqli_data_seek($result,0); while($p = mysqli_fetch_assoc($result)): ?>
                <div class="list-item">
                    <div class="list-thumb">
                        <?php if($p['image_path']): ?>
                            <img src="<?php echo htmlspecialchars($p['image_path']); ?>" alt="">
                        <?php else: ?><i class="fas fa-gamepad"></i><?php endif; ?>
                    </div>
                    <div class="list-info">
                        <div class="list-name"><?php echo htmlspecialchars($p['title']); ?></div>
                        <div class="list-meta">
                            <?php if(!empty($p['game_name'])): ?><span class="tag tag-game"><i class="fas fa-gamepad"></i> <?php echo htmlspecialchars($p['game_name']); ?></span><?php endif; ?>
                            <?php if(!empty($p['item_type']) && isset($item_types[$p['item_type']])): ?><span class="tag tag-spec"><?php echo $item_types[$p['item_type']]; ?></span><?php endif; ?>
                            <?php if(!empty($p['account_level'])): ?><span class="tag tag-spec">Lvl <?php echo $p['account_level']; ?></span><?php endif; ?>
                            <?php if(!empty($p['account_rank'])): ?><span class="tag tag-spec"><?php echo htmlspecialchars($p['account_rank']); ?></span><?php endif; ?>
                            <?php if(!empty($p['server_region'])): ?><span class="tag tag-spec"><?php echo htmlspecialchars($p['server_region']); ?></span><?php endif; ?>
                        </div>
                        <div class="list-price">$<?php echo number_format($p['price'],2); ?></div>
                        <div class="list-seller">
                            <div class="seller-ava" style="width:20px;height:20px;font-size:0.55rem;"><?php echo strtoupper(substr($p['seller_name'],0,2)); ?></div>
                            <?php echo htmlspecialchars($p['seller_name']); ?>
                            <?php if($p['seller_rating'] > 0): ?>&nbsp;<i class="fas fa-star" style="color:var(--gold);font-size:0.7rem;"></i> <?php echo number_format($p['seller_rating'],1); ?><?php endif; ?>
                        </div>
                    </div>
                    <div class="list-actions">
                        <a href="product-detail.php?id=<?php echo $p['id']; ?>" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> View</a>
                        <?php if($my_products): ?>
                            <a href="edit-product.php?id=<?php echo $p['id']; ?>" class="btn btn-ghost btn-sm"><i class="fas fa-pen"></i> Edit</a>
                            <a href="delete-product.php?id=<?php echo $p['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this product?')"><i class="fas fa-trash"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <?php else: ?>
            <div class="empty">
                <div class="empty-icon"><i class="fas fa-store"></i></div>
                <h3>No Products Found</h3>
                <p><?php echo $my_products ? "You haven't listed any products yet." : "No products match your current filters. Try broadening your search."; ?></p>
                <?php if(!$my_products): ?>
                    <a href="products.php" class="btn btn-primary"><i class="fas fa-rotate-left"></i> View All Products</a>
                <?php else: ?>
                    <a href="add-product.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Your First Product</a>
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

    const gridBtn = document.getElementById('gridViewBtn');
    const listBtn = document.getElementById('listViewBtn');
    const gridEl  = document.getElementById('productsGrid');
    const listEl  = document.getElementById('productsList');

    gridBtn.addEventListener('click', () => {
        gridBtn.classList.add('active'); listBtn.classList.remove('active');
        gridEl.style.display = 'grid';  listEl.style.display = 'none';
    });
    listBtn.addEventListener('click', () => {
        listBtn.classList.add('active'); gridBtn.classList.remove('active');
        gridEl.style.display = 'none';  listEl.style.display = 'flex';
    });

    const filterToggle  = document.getElementById('filterToggle');
    const filterBody    = document.getElementById('filterBody');
    const filterChevron = document.getElementById('filterChevron');
    let open = true;
    filterToggle.addEventListener('click', () => {
        open = !open;
        filterBody.style.display = open ? 'block' : 'none';
        filterChevron.classList.toggle('collapsed', !open);
    });
</script>
</body>
</html>