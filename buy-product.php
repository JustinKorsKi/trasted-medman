<?php
require_once 'includes/config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'buyer') {
    header('Location: login.php'); exit();
}

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$query = "SELECT p.*, u.username as seller_name, u.id as seller_id, u.rating as seller_rating
          FROM products p
          JOIN users u ON p.seller_id = u.id
          WHERE p.id = $product_id AND p.status = 'available'";

$result = mysqli_query($conn, $query);
if(mysqli_num_rows($result) == 0) { header('Location: products.php'); exit(); }
$product = mysqli_fetch_assoc($result);

$midmen = mysqli_query($conn, "SELECT id, username, rating, midman_rating, total_midman_ratings FROM users WHERE role='midman' AND is_verified=1");

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $midman_id   = intval($_POST['midman_id']);
    $service_fee = $product['price'] * 0.05;
    $q = "INSERT INTO transactions (product_id, buyer_id, seller_id, midman_id, amount, service_fee, status)
          VALUES ($product_id, {$_SESSION['user_id']}, {$product['seller_id']}, $midman_id, {$product['price']}, $service_fee, 'pending')";
    if(mysqli_query($conn, $q)) {
        $tid = mysqli_insert_id($conn);
        mysqli_query($conn, "UPDATE products SET status='sold' WHERE id=$product_id");
        $_SESSION['success'] = 'Transaction started! Chat with your midman and seller below.';
        header("Location: transaction-chat.php?id=$tid"); exit();
    }
}

$service_fee = $product['price'] * 0.05;
$total       = $product['price'] + $service_fee;
$item_icons  = ['account'=>'fa-gamepad','currency'=>'fa-coins','item'=>'fa-box-open','service'=>'fa-bolt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy — <?php echo htmlspecialchars($product['title']); ?> · Trusted Midman</title>
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

        @keyframes fadeUp { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);} }

        /* ── SIDEBAR ── */
        .sidebar { width:var(--sidebar-w); background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; height:100vh; z-index:100; transition:transform 0.35s cubic-bezier(.77,0,.18,1); }
        .sidebar::before { content:''; position:absolute; bottom:-80px; left:-80px; width:300px; height:300px; background:radial-gradient(circle,rgba(200,100,0,0.08) 0%,transparent 65%); pointer-events:none; }
        .sidebar-logo { display:flex; align-items:center; gap:12px; padding:26px 22px; text-decoration:none; border-bottom:1px solid var(--border); position:relative; z-index:1; }
        .logo-icon { width:38px; height:38px; background:linear-gradient(135deg,var(--gold),#d4920a); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#0f0c08; font-size:16px; flex-shrink:0; box-shadow:0 0 20px var(--gold-glow); }
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
        .back-link { font-size:0.82rem; color:var(--text-muted); text-decoration:none; display:flex; align-items:center; gap:6px; transition:color 0.2s; }
        .back-link:hover { color:var(--gold); }
        .content { padding:28px 32px; flex:1; }

        /* ── BREADCRUMB ── */
        .breadcrumb { display:flex; align-items:center; gap:8px; font-size:0.78rem; color:var(--text-dim); margin-bottom:24px; }
        .breadcrumb a { color:var(--text-muted); text-decoration:none; transition:color 0.2s; }
        .breadcrumb a:hover { color:var(--gold); }
        .breadcrumb i { font-size:0.6rem; }

        /* ── LAYOUT ── */
        .buy-grid { display:grid; grid-template-columns:1fr 380px; gap:24px; align-items:start; }

        /* ── PANELS ── */
        .panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden; margin-bottom:20px;
            opacity:0; transform:translateY(10px); animation:fadeUp 0.45s ease forwards;
            position:relative;
        }
        .panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.15),transparent); z-index:1; }
        .panel:nth-child(2){animation-delay:.06s;} .panel:nth-child(3){animation-delay:.12s;} .panel:nth-child(4){animation-delay:.18s;}

        .panel-head { display:flex; align-items:center; gap:10px; padding:15px 20px; border-bottom:1px solid var(--border); }
        .ph-icon { width:28px; height:28px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.78rem; border:1px solid transparent; }
        .ph-gold   { background:var(--gold-dim);   color:var(--gold);   border-color:rgba(240,165,0,0.14); }
        .ph-purple { background:var(--purple-dim); color:var(--purple); border-color:rgba(160,100,255,0.14); }
        .ph-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .ph-title  { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .panel-body { padding:20px; }

        /* ── PRODUCT SHOWCASE ── */
        .product-showcase { display:flex; gap:16px; align-items:flex-start; }
        .prod-img { width:120px; height:90px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); overflow:hidden; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:var(--text-dim); font-size:2rem; }
        .prod-img img { width:100%; height:100%; object-fit:cover; }
        .prod-info { flex:1; min-width:0; }
        .prod-title { font-family:var(--font-head); font-size:1.15rem; font-weight:800; color:var(--text); margin-bottom:8px; line-height:1.2; letter-spacing:-0.01em; }
        .prod-tags  { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px; }
        .prod-tag   { font-size:0.68rem; font-weight:700; padding:3px 9px; border-radius:10px; display:inline-flex; align-items:center; gap:4px; border:1px solid transparent; }
        .tag-game   { background:var(--gold-dim);   color:var(--gold);   border-color:rgba(240,165,0,0.14); }
        .tag-type   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .tag-cond   { background:var(--purple-dim); color:var(--purple); border-color:rgba(160,100,255,0.14); }
        .prod-price { font-family:var(--font-head); font-size:1.9rem; font-weight:800; color:var(--gold); line-height:1; letter-spacing:-0.01em; }

        .seller-strip { display:flex; align-items:center; gap:10px; margin-top:12px; padding-top:12px; border-top:1px solid var(--border); }
        .seller-ava   { width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg,var(--gold),#c47d00); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.7rem; color:#0f0c08; flex-shrink:0; }
        .seller-name  { font-size:0.84rem; font-weight:600; color:var(--text-warm); }
        .seller-rating { display:flex; align-items:center; gap:3px; font-size:0.72rem; color:var(--text-muted); }

        /* ── MIDMAN CARDS ── */
        .midman-list { display:flex; flex-direction:column; gap:10px; }
        .midman-card {
            display:flex; align-items:center; gap:14px;
            padding:14px 16px; background:var(--surface2);
            border:2px solid var(--border); border-radius:var(--radius-sm);
            cursor:pointer; transition:all 0.22s ease; position:relative;
        }
        .midman-card:hover { border-color:var(--border2); transform:translateX(3px); background:var(--surface3); }
        .midman-card.selected { border-color:var(--gold); background:rgba(240,165,0,0.07); }
        .midman-card input[type="radio"] { position:absolute; opacity:0; pointer-events:none; }

        .midman-card-ava { width:44px; height:44px; border-radius:50%; background:linear-gradient(135deg,var(--purple),#6030c0); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:700; font-size:0.9rem; color:white; flex-shrink:0; transition:background 0.22s; }
        .midman-card.selected .midman-card-ava { background:linear-gradient(135deg,var(--gold),#c47d00); color:#0f0c08; }

        .midman-card-info { flex:1; min-width:0; }
        .midman-card-name  { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); margin-bottom:3px; letter-spacing:-0.01em; }
        .midman-card-stars { display:flex; align-items:center; gap:3px; }
        .midman-card-stars i { font-size:0.65rem; }
        .midman-card-stars span { font-size:0.72rem; color:var(--text-muted); margin-left:2px; }

        .midman-check { width:20px; height:20px; border-radius:50%; border:2px solid var(--border2); display:flex; align-items:center; justify-content:center; font-size:0.6rem; color:transparent; flex-shrink:0; transition:all 0.22s; }
        .midman-card.selected .midman-check { background:var(--gold); border-color:var(--gold); color:#0f0c08; }

        .no-midmen { background:var(--red-dim); border:1px solid rgba(255,77,109,0.2); border-radius:var(--radius-sm); padding:14px 16px; display:flex; align-items:center; gap:10px; font-size:0.875rem; color:#ff7090; }

        /* ── HOW IT WORKS ── */
        .steps-list { display:flex; flex-direction:column; gap:12px; }
        .step-item  { display:flex; align-items:flex-start; gap:11px; }
        .step-num   { width:22px; height:22px; border-radius:50%; background:var(--gold-dim); color:var(--gold); border:1px solid rgba(240,165,0,0.2); font-family:var(--font-head); font-size:0.65rem; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; margin-top:1px; }
        .step-text  { font-size:0.84rem; color:var(--text-muted); line-height:1.6; }

        /* ── ORDER SUMMARY ── */
        .sticky-col { position:sticky; top:80px; }
        .pay-row { display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid var(--border); font-size:0.875rem; }
        .pay-row:last-child { border-bottom:none; }
        .pay-key { color:var(--text-muted); }
        .pay-val { font-weight:600; color:var(--text-warm); }
        .pay-total .pay-key { font-family:var(--font-head); font-weight:700; font-size:1rem; color:var(--text); letter-spacing:-0.01em; }
        .pay-total .pay-val { font-family:var(--font-head); font-size:1.4rem; color:var(--gold); letter-spacing:-0.01em; }

        /* ── BUTTONS ── */
        .btn-submit {
            width:100%; padding:14px; margin-bottom:10px;
            background:linear-gradient(135deg,var(--gold),#d48500);
            color:#0f0c08; font-family:var(--font-head);
            font-size:1rem; font-weight:800; letter-spacing:-0.01em;
            border:none; border-radius:var(--radius-sm); cursor:pointer;
            display:flex; align-items:center; justify-content:center; gap:10px;
            box-shadow:0 6px 24px var(--gold-glow), 0 1px 0 rgba(255,255,255,0.1) inset;
            transition:all 0.24s ease;
        }
        .btn-submit:hover { background:linear-gradient(135deg,var(--gold-lt),var(--gold)); transform:translateY(-2px); box-shadow:0 10px 32px rgba(240,165,0,0.4); }
        .btn-submit:disabled { opacity:0.4; cursor:not-allowed; transform:none; box-shadow:none; }

        .btn-back {
            width:100%; padding:11px; background:var(--surface2);
            color:var(--text-muted); border:1px solid var(--border2);
            border-radius:var(--radius-sm); font-family:var(--font-body);
            font-size:0.875rem; font-weight:600; cursor:pointer;
            text-decoration:none; display:flex; align-items:center;
            justify-content:center; gap:8px; transition:all 0.2s;
        }
        .btn-back:hover { color:var(--text-warm); border-color:var(--border3); }

        .trust-row  { display:flex; justify-content:center; gap:16px; margin-top:14px; flex-wrap:wrap; }
        .trust-badge { display:flex; align-items:center; gap:5px; font-size:0.7rem; color:var(--text-dim); }
        .trust-badge i { color:var(--teal); font-size:0.7rem; }

        .select-hint { font-size:0.75rem; color:var(--text-dim); text-align:center; margin-top:8px; transition:color 0.2s; }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:1100px) { :root{--sidebar-w:220px;} }
        @media(max-width:900px)  { .buy-grid{grid-template-columns:1fr;} .sticky-col{position:static;} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
        @media(max-width:560px)  { .product-showcase{flex-direction:column;} .prod-img{width:100%;height:160px;} }
    </style>
</head>
<body>
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <!-- SIDEBAR — buyer only (this page is buyer-restricted) -->
    <aside class="sidebar" id="sidebar">
        <a href="index.php" class="sidebar-logo">
            <div class="logo-icon"><i class="fas fa-shield-halved"></i></div>
            <div class="logo-text">Trusted Midman <span class="logo-sub">Marketplace</span></div>
        </a>
        <nav class="sidebar-nav">
            <div class="nav-label">Buyer</div>
            <a href="buyer-dashboard.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
            <a href="products.php"         class="nav-link active"><span class="nav-icon"><i class="fas fa-store"></i></span> Browse Products</a>
            <a href="my-transactions.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> My Purchases</a>
            <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span>Dispute Center</a>
            <div class="nav-label" style="margin-top:10px;">Account</div>
             <a href="verify-identity.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span>Apply as Midman</a>
            <a href="profile.php"          class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a>
            <a href="logout.php"           class="nav-link" style="color:var(--text-dim);margin-top:6px;"><span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="ava"><?php echo strtoupper(substr($_SESSION['username'],0,2)); ?></div>
                <div>
                    <div class="pill-name"><?php echo htmlspecialchars($_SESSION['full_name']??$_SESSION['username']); ?></div>
                    <div class="pill-role">Buyer</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN -->
    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <span class="page-title">Purchase Product</span>
            </div>
            <a href="product-detail.php?id=<?php echo $product_id; ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Product
            </a>
        </header>

        <div class="content">

            <div class="breadcrumb">
                <a href="products.php">Products</a>
                <i class="fas fa-chevron-right"></i>
                <a href="product-detail.php?id=<?php echo $product_id; ?>"><?php echo htmlspecialchars($product['title']); ?></a>
                <i class="fas fa-chevron-right"></i>
                <span>Purchase</span>
            </div>

            <form method="POST" id="buyForm">
            <div class="buy-grid">

                <!-- LEFT COLUMN -->
                <div>

                    <!-- Product -->
                    <div class="panel">
                        <div class="panel-head">
                            <div class="ph-icon ph-gold"><i class="fas fa-box-open"></i></div>
                            <span class="ph-title">You're Buying</span>
                        </div>
                        <div class="panel-body">
                            <div class="product-showcase">
                                <div class="prod-img">
                                    <?php if($product['image_path']): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="">
                                    <?php else: ?>
                                        <i class="fas fa-gamepad"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="prod-info">
                                    <div class="prod-title"><?php echo htmlspecialchars($product['title']); ?></div>
                                    <div class="prod-tags">
                                        <?php if(!empty($product['game_name'])): ?>
                                            <span class="prod-tag tag-game"><i class="fas fa-gamepad"></i> <?php echo htmlspecialchars($product['game_name']); ?></span>
                                        <?php endif; ?>
                                        <?php if(!empty($product['item_type'])): ?>
                                            <span class="prod-tag tag-type"><i class="fas <?php echo $item_icons[$product['item_type']]??'fa-tag'; ?>"></i> <?php echo ucfirst($product['item_type']); ?></span>
                                        <?php endif; ?>
                                        <?php if(!empty($product['condition'])): ?>
                                            <span class="prod-tag tag-cond"><?php echo ucfirst($product['condition']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="prod-price">$<?php echo number_format($product['price'],2); ?></div>
                                    <div class="seller-strip">
                                        <div class="seller-ava"><?php echo strtoupper(substr($product['seller_name'],0,2)); ?></div>
                                        <div>
                                            <div class="seller-name"><?php echo htmlspecialchars($product['seller_name']); ?></div>
                                            <div class="seller-rating">
                                                <?php for($i=1;$i<=5;$i++): ?>
                                                    <i class="fas fa-star" style="color:<?php echo $i<=round($product['seller_rating']??0)?'var(--gold)':'var(--text-dim)';?>"></i>
                                                <?php endfor; ?>
                                                <span style="margin-left:3px;"><?php echo number_format($product['seller_rating']??0,1); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Midman Selection -->
                    <div class="panel">
                        <div class="panel-head">
                            <div class="ph-icon ph-purple"><i class="fas fa-handshake"></i></div>
                            <span class="ph-title">Choose Your Midman</span>
                        </div>
                        <div class="panel-body">
                            <?php if(mysqli_num_rows($midmen) > 0): ?>
                            <div class="midman-list">
                                <?php while($m = mysqli_fetch_assoc($midmen)):
                                    $mr      = round($m['midman_rating'] ?? $m['rating'] ?? 0);
                                    $mrval   = number_format($m['midman_rating'] ?? $m['rating'] ?? 0, 1);
                                    $reviews = $m['total_midman_ratings'] ?? 0;
                                ?>
                                <label class="midman-card">
                                    <input type="radio" name="midman_id" value="<?php echo $m['id']; ?>" required>
                                    <div class="midman-card-ava"><?php echo strtoupper(substr($m['username'],0,2)); ?></div>
                                    <div class="midman-card-info">
                                        <div class="midman-card-name"><?php echo htmlspecialchars($m['username']); ?></div>
                                        <div class="midman-card-stars">
                                            <?php for($i=1;$i<=5;$i++): ?>
                                                <i class="fas fa-star" style="color:<?php echo $i<=$mr?'var(--gold)':'var(--text-dim)';?>"></i>
                                            <?php endfor; ?>
                                            <span><?php echo $mrval; echo $reviews>0?" · $reviews reviews":" · No reviews yet"; ?></span>
                                        </div>
                                    </div>
                                    <div class="midman-check"><i class="fas fa-check"></i></div>
                                </label>
                                <?php endwhile; ?>
                            </div>
                            <?php else: ?>
                            <div class="no-midmen">
                                <i class="fas fa-triangle-exclamation"></i>
                                No verified midmen available right now. Please try again later.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- How It Works -->
                    <div class="panel">
                        <div class="panel-head">
                            <div class="ph-icon ph-teal"><i class="fas fa-circle-info"></i></div>
                            <span class="ph-title">How It Works</span>
                        </div>
                        <div class="panel-body">
                            <div class="steps-list">
                                <div class="step-item"><div class="step-num">1</div><div class="step-text">Choose a verified midman to oversee your transaction.</div></div>
                                <div class="step-item"><div class="step-num">2</div><div class="step-text">A group chat opens between you, the seller, and the midman.</div></div>
                                <div class="step-item"><div class="step-num">3</div><div class="step-text">Send payment to the midman via your agreed method (PayPal, GCash, etc.).</div></div>
                                <div class="step-item"><div class="step-num">4</div><div class="step-text">Midman confirms receipt; seller delivers the item to you.</div></div>
                                <div class="step-item"><div class="step-num">5</div><div class="step-text">Verify the item, then instruct the midman to release payment to the seller.</div></div>
                                <div class="step-item"><div class="step-num">6</div><div class="step-text">Rate your midman and seller to help the community.</div></div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- RIGHT COLUMN (sticky) -->
                <div class="sticky-col">

                    <!-- Order Summary -->
                    <div class="panel">
                        <div class="panel-head">
                            <div class="ph-icon ph-gold"><i class="fas fa-coins"></i></div>
                            <span class="ph-title">Order Summary</span>
                        </div>
                        <div class="panel-body">
                            <div class="pay-row"><span class="pay-key">Product Price</span><span class="pay-val">$<?php echo number_format($product['price'],2); ?></span></div>
                            <div class="pay-row"><span class="pay-key">Midman Fee (5%)</span><span class="pay-val">$<?php echo number_format($service_fee,2); ?></span></div>
                            <div class="pay-row pay-total"><span class="pay-key">Total</span><span class="pay-val">$<?php echo number_format($total,2); ?></span></div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="panel">
                        <div class="panel-body">
                            <button type="submit" class="btn-submit" id="submitBtn" disabled>
                                <i class="fas fa-shield-halved"></i> Start Protected Transaction
                            </button>
                            <div class="select-hint" id="selHint">Select a midman above to continue</div>
                            <div class="trust-row">
                                <div class="trust-badge"><i class="fas fa-lock"></i> Escrow Protected</div>
                                <div class="trust-badge"><i class="fas fa-shield-halved"></i> Verified Midmen</div>
                                <div class="trust-badge"><i class="fas fa-scale-balanced"></i> Dispute Support</div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
            </form>

        </div>
    </main>
</div>

<script>
    const ham = document.getElementById('hamburger');
    const sb  = document.getElementById('sidebar');
    const ov  = document.getElementById('overlay');
    ham.addEventListener('click', () => { sb.classList.toggle('open'); ov.classList.toggle('visible'); });
    ov.addEventListener('click',  () => { sb.classList.remove('open'); ov.classList.remove('visible'); });

    const cards  = document.querySelectorAll('.midman-card');
    const submit = document.getElementById('submitBtn');
    const hint   = document.getElementById('selHint');

    cards.forEach(card => {
        card.addEventListener('click', () => {
            cards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            card.querySelector('input[type="radio"]').checked = true;
            submit.disabled = false;
            hint.textContent = '✓ Midman selected — ready to proceed';
            hint.style.color = 'var(--teal)';
        });
    });

    document.getElementById('buyForm').addEventListener('submit', e => {
        if(!document.querySelector('.midman-card.selected')) {
            e.preventDefault();
            hint.textContent = '⚠ Please select a midman first';
            hint.style.color = 'var(--gold)';
        }
    });
</script>
</body>
</html>