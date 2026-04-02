<?php
require_once 'includes/config.php';
if(!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id        = $_SESSION['user_id'];
$user_role      = $_SESSION['role'];          // 'buyer' or 'seller'
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch transaction details – must be completed and have a midman
$query = "SELECT t.*, p.title as product_title,
          u.username as midman_name, u.id as midman_id
          FROM transactions t
          JOIN products p ON t.product_id = p.id
          JOIN users u ON t.midman_id = u.id
          WHERE t.id = $transaction_id AND t.status='completed'
          AND (t.buyer_id=$user_id OR t.seller_id=$user_id)";

$result = mysqli_query($conn, $query);
if(mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = 'Transaction not found or not eligible.';
    header('Location: my-transactions.php');
    exit();
}
$transaction = mysqli_fetch_assoc($result);

// Check if user already rated this midman for this transaction
$check_rating = mysqli_query($conn, "SELECT * FROM midman_ratings WHERE transaction_id=$transaction_id AND rater_id=$user_id");
$already_rated = mysqli_num_rows($check_rating) > 0;
$my_rating = $already_rated ? mysqli_fetch_assoc($check_rating) : null;

if($_SERVER['REQUEST_METHOD']=='POST' && !$already_rated) {
    $rating = intval($_POST['rating']);
    $review = mysqli_real_escape_string($conn, $_POST['review']);
    if($rating >= 1 && $rating <= 5) {
        mysqli_query($conn, "INSERT INTO midman_ratings (transaction_id, midman_id, rater_id, rating, review)
                             VALUES ($transaction_id, {$transaction['midman_id']}, $user_id, $rating, '$review')");
        $avg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(rating) as a, COUNT(*) as c FROM midman_ratings WHERE midman_id={$transaction['midman_id']}"));
        mysqli_query($conn, "UPDATE users SET midman_rating={$avg['a']}, total_midman_ratings={$avg['c']} WHERE id={$transaction['midman_id']}");
        $_SESSION['success'] = 'Thank you for rating the midman!';
        header("Location: transaction-detail.php?id=$transaction_id");
        exit();
    }
}

// Get all ratings for this transaction (for display)
$all_ratings = mysqli_query($conn, "SELECT mr.*, u.username as rater_name, u.role FROM midman_ratings mr
                                    JOIN users u ON mr.rater_id=u.id
                                    WHERE mr.transaction_id=$transaction_id");

$display_name = $_SESSION['full_name'] ?? $_SESSION['username'];
$back_link = $user_role == 'buyer' ? 'my-transactions.php' : 'my-sales.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Midman — Trusted Midman</title>
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
        .main { margin-left:var(--sidebar-w); flex:1; min-height:100vh; display:flex; flex-direction:column; }
        .topbar { position:sticky; top:0; z-index:50; background:rgba(15,12,8,0.88); backdrop-filter:blur(24px); border-bottom:1px solid var(--border); padding:0 32px; height:64px; display:flex; align-items:center; justify-content:space-between; }
        .topbar-left { display:flex; align-items:center; gap:16px; }
        .hamburger { display:none; background:none; border:none; color:var(--text-muted); font-size:1.1rem; cursor:pointer; padding:6px; border-radius:7px; transition:color 0.2s; }
        .hamburger:hover { color:var(--text-warm); }
        .page-title { font-family:var(--font-head); font-size:1.15rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .back-link { font-size:0.82rem; color:var(--text-muted); text-decoration:none; display:flex; align-items:center; gap:6px; transition:color 0.2s; }
        .back-link:hover { color:var(--gold); }
        .content { padding:28px 32px; flex:1; max-width:720px; }

        @keyframes fadeUp { from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);} }

        /* ── MIDMAN HERO ── */
        .midman-hero {
            background:var(--surface); border:1px solid var(--border2);
            border-radius:var(--radius-lg); padding:28px 32px; margin-bottom:22px;
            position:relative; overflow:hidden;
            opacity:0; animation:fadeUp 0.45s ease forwards;
        }
        .midman-hero::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,var(--gold-glow),transparent); }
        .midman-hero::after { content:''; position:absolute; top:-60px; right:-60px; width:200px; height:200px; background:radial-gradient(circle,var(--gold-glow) 0%,transparent 65%); pointer-events:none; }

        .hero-flex { display:flex; align-items:center; gap:20px; flex-wrap:wrap; position:relative; z-index:1; }
        .midman-avatar { width:64px; height:64px; border-radius:50%; background:linear-gradient(135deg,var(--purple),#7040cc); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-weight:800; font-size:1.3rem; color:white; flex-shrink:0; box-shadow:0 0 0 3px var(--purple-dim); }
        .midman-info { flex:1; min-width:0; }
        .midman-name { font-family:var(--font-head); font-size:1.3rem; font-weight:800; color:var(--text); margin-bottom:4px; letter-spacing:-0.01em; }
        .midman-sub  { font-size:0.78rem; color:var(--text-muted); }
        .midman-badge { background:var(--purple-dim); color:var(--purple); padding:6px 12px; border-radius:30px; font-size:0.7rem; font-weight:700; display:inline-flex; align-items:center; gap:6px; border:1px solid rgba(160,100,255,0.14); }

        /* ── TRANSACTION STRIP ── */
        .tx-strip { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px; opacity:0; animation:fadeUp 0.45s ease 0.07s forwards; }
        .tx-chip { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius-sm); padding:12px 16px; flex:1; min-width:120px; }
        .tx-chip-lbl { font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.09em; color:var(--text-dim); margin-bottom:4px; }
        .tx-chip-val { font-family:var(--font-head); font-size:1rem; font-weight:700; color:var(--text-warm); }

        /* ── PANELS ── */
        .panel {
            background:var(--surface); border:1px solid var(--border);
            border-radius:var(--radius); overflow:hidden; margin-bottom:20px;
            opacity:0; transform:translateY(10px); animation:fadeUp 0.45s ease forwards;
            position:relative;
        }
        .panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,var(--gold-dim),transparent); z-index:1; }
        .panel:nth-child(3){animation-delay:.10s;} .panel:nth-child(4){animation-delay:.15s;}

        .panel-head { display:flex; align-items:center; gap:10px; padding:16px 22px; border-bottom:1px solid var(--border); }
        .ph-icon { width:28px; height:28px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:0.78rem; border:1px solid transparent; }
        .ph-purple { background:var(--purple-dim); color:var(--purple); border-color:rgba(160,100,255,0.14); }
        .ph-gold   { background:var(--gold-dim);   color:var(--gold);   border-color:rgba(240,165,0,0.14); }
        .ph-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .ph-title  { font-family:var(--font-head); font-size:0.92rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .panel-body { padding:20px 22px; }

        /* ── STAR RATING ── */
        .star-label { font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.09em; color:var(--text-muted); display:block; margin-bottom:10px; }
        .stars-input { display:flex; flex-direction:row-reverse; gap:8px; width:fit-content; margin-bottom:6px; }
        .stars-input input { display:none; }
        .stars-input label { font-size:2rem; color:var(--text-dim); cursor:pointer; transition:color 0.15s, transform 0.15s; }
        .stars-input label:hover,
        .stars-input label:hover ~ label,
        .stars-input input:checked ~ label { color:var(--gold); transform:scale(1.05); }
        .rating-hint { font-size:0.72rem; color:var(--text-dim); min-height:20px; }

        .form-label { font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.09em; color:var(--text-muted); display:block; margin-bottom:8px; }
        .form-textarea { width:100%; padding:12px 14px; background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius-sm); color:var(--text-warm); font-family:var(--font-body); font-size:0.875rem; resize:vertical; min-height:100px; transition:all 0.2s; outline:none; }
        .form-textarea::placeholder { color:var(--text-dim); }
        .form-textarea:focus { border-color:var(--gold); box-shadow:0 0 0 3px var(--gold-dim); }

        /* buttons */
        .btn { display:inline-flex; align-items:center; gap:8px; padding:11px 22px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.875rem; font-weight:600; text-decoration:none; cursor:pointer; border:none; transition:all 0.22s ease; letter-spacing:0.01em; }
        .btn-gold { background:linear-gradient(135deg,var(--gold),#d48500); color:#0f0c08; font-weight:700; box-shadow:0 3px 14px var(--gold-glow); }
        .btn-gold:hover { background:linear-gradient(135deg,var(--gold-lt),var(--gold)); transform:translateY(-2px); }
        .btn-ghost { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); }
        .btn-ghost:hover { color:var(--text-warm); border-color:var(--border3); background:var(--surface3); transform:translateY(-2px); }
        .btn-row { display:flex; gap:12px; flex-wrap:wrap; margin-top:20px; }

        /* already rated */
        .rated-box { background:var(--teal-dim); border:1px solid rgba(0,212,170,0.2); border-radius:var(--radius-sm); padding:18px 20px; }
        .rated-title { font-family:var(--font-head); font-size:0.9rem; font-weight:700; color:var(--teal); margin-bottom:8px; display:flex; align-items:center; gap:8px; }
        .my-stars { display:flex; gap:4px; margin:8px 0; }
        .my-stars i { font-size:0.9rem; }
        .rated-review { font-size:0.85rem; color:var(--text-muted); margin-top:8px; font-style:italic; }

        /* reviews list */
        .review-item { padding:16px 0; border-bottom:1px solid var(--border); }
        .review-item:last-child { border-bottom:none; }
        .rev-top { display:flex; align-items:center; gap:12px; margin-bottom:10px; flex-wrap:wrap; }
        .rev-ava { width:36px; height:36px; border-radius:50%; background:var(--purple-dim); color:var(--purple); display:flex; align-items:center; justify-content:center; font-family:var(--font-head); font-size:0.8rem; font-weight:700; flex-shrink:0; }
        .rev-name { font-size:0.9rem; font-weight:600; color:var(--text-warm); }
        .rev-role { font-size:0.65rem; background:var(--surface2); color:var(--text-dim); padding:2px 8px; border-radius:20px; margin-left:4px; }
        .rev-stars { display:flex; gap:3px; margin-left:auto; }
        .rev-stars i { font-size:0.7rem; }
        .rev-text { font-size:0.84rem; color:var(--text-muted); line-height:1.55; margin-top:4px; padding-left:48px; }
        .rev-date { font-size:0.7rem; color:var(--text-dim); margin-top:6px; padding-left:48px; }

        /* empty */
        .empty { text-align:center; padding:36px 24px; color:var(--text-muted); }
        .empty i { font-size:2rem; margin-bottom:12px; opacity:0.3; display:block; color:var(--text-dim); }
        .empty h4 { font-family:var(--font-head); font-size:1rem; color:var(--text-warm); margin-bottom:6px; letter-spacing:-0.01em; }

        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:1100px) { :root{--sidebar-w:220px;} }
        @media(max-width:820px) { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} .hero-flex{flex-direction:column;align-items:flex-start;} }
    </style>
</head>
<body>
<div class="layout">
    <div class="sidebar-overlay" id="overlay"></div>

    <!-- SIDEBAR (dynamic by role) -->
    <aside class="sidebar" id="sidebar">
        <a href="index.php" class="sidebar-logo">
            <div class="logo-icon"><i class="fas fa-shield-halved"></i></div>
            <div class="logo-text">Trusted Midman <span class="logo-sub">Marketplace</span></div>
        </a>
        <nav class="sidebar-nav">
            <?php if($user_role == 'seller'): ?>
                <div class="nav-label">Seller</div>
                <a href="seller-dashboard.php" class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="my-products.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-box-open"></i></span> My Products</a>
                <a href="add-product.php"      class="nav-link"><span class="nav-icon"><i class="fas fa-plus-circle"></i></span> Add Product</a>
                <a href="my-transactions.php"  class="nav-link active"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> Transactions</a>
                <a href="my-sales.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-chart-line"></i></span> Sales</a>
                <a href="seller-earnings.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-coins"></i></span> Earnings</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <?php else: // buyer ?>
                <div class="nav-label">Buyer</div>
                <a href="buyer-dashboard.php"  class="nav-link"><span class="nav-icon"><i class="fas fa-gauge-high"></i></span> Dashboard</a>
                <a href="products.php"         class="nav-link"><span class="nav-icon"><i class="fas fa-store"></i></span> Browse Products</a>
                <a href="my-transactions.php"  class="nav-link active"><span class="nav-icon"><i class="fas fa-bag-shopping"></i></span> My Purchases</a>
                <a href="raise-dispute.php"    class="nav-link"><span class="nav-icon"><i class="fas fa-scale-balanced"></i></span> Dispute Center</a>
            <?php endif; ?>

            <div class="nav-label" style="margin-top:10px;">Account</div>
            <?php if($user_role !== 'midman'): ?>
                <a href="verify-identity.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-check"></i></span> Apply as Midman</a>
            <?php endif; ?>
            <a href="profile.php" class="nav-link"><span class="nav-icon"><i class="fas fa-user-circle"></i></span> Profile</a>
            <a href="logout.php"  class="nav-link" style="color:var(--text-dim);margin-top:6px;"><span class="nav-icon"><i class="fas fa-arrow-right-from-bracket"></i></span> Sign Out</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-pill">
                <div class="ava"><?php echo strtoupper(substr($_SESSION['username']??'GU',0,2)); ?></div>
                <div>
                    <div class="pill-name"><?php echo htmlspecialchars($display_name); ?></div>
                    <div class="pill-role"><?php echo ucfirst($user_role); ?></div>
                </div>
            </div>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
                <span class="page-title">Rate Your Midman</span>
            </div>
            <a href="<?php echo $back_link; ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </header>

        <div class="content">

            <!-- MIDMAN HERO -->
            <div class="midman-hero">
                <div class="hero-flex">
                    <div class="midman-avatar"><?php echo strtoupper(substr($transaction['midman_name'],0,2)); ?></div>
                    <div class="midman-info">
                        <div class="midman-name"><?php echo htmlspecialchars($transaction['midman_name']); ?></div>
                        <div class="midman-sub">Transaction #<?php echo $transaction_id; ?></div>
                    </div>
                    <div class="midman-badge">
                        <i class="fas fa-handshake"></i> Midman
                    </div>
                </div>
            </div>

            <!-- TRANSACTION INFO -->
            <div class="tx-strip">
                <div class="tx-chip">
                    <div class="tx-chip-lbl">Product</div>
                    <div class="tx-chip-val"><?php echo htmlspecialchars($transaction['product_title']); ?></div>
                </div>
                <div class="tx-chip">
                    <div class="tx-chip-lbl">Amount</div>
                    <div class="tx-chip-val">$<?php echo number_format($transaction['amount'],2); ?></div>
                </div>
                <div class="tx-chip">
                    <div class="tx-chip-lbl">Status</div>
                    <div class="tx-chip-val" style="color:var(--teal);">Completed</div>
                </div>
            </div>

            <?php if($already_rated): ?>
                <!-- ALREADY RATED PANEL -->
                <div class="panel">
                    <div class="panel-head">
                        <div class="ph-icon ph-teal"><i class="fas fa-circle-check"></i></div>
                        <span class="ph-title">Rating Already Submitted</span>
                    </div>
                    <div class="panel-body">
                        <div class="rated-box">
                            <div class="rated-title"><i class="fas fa-star"></i> Your Rating</div>
                            <div class="my-stars">
                                <?php for($i=1;$i<=5;$i++): ?>
                                    <i class="fas fa-star" style="color:<?php echo $i<=$my_rating['rating']?'var(--gold)':'var(--text-dim)';?>"></i>
                                <?php endfor; ?>
                            </div>
                            <?php if($my_rating['review']): ?>
                                <div class="rated-review">"<?php echo htmlspecialchars($my_rating['review']); ?>"</div>
                            <?php endif; ?>
                        </div>
                        <div class="btn-row">
                            <a href="<?php echo $back_link; ?>" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Back</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- RATING FORM -->
                <div class="panel">
                    <div class="panel-head">
                        <div class="ph-icon ph-purple"><i class="fas fa-star"></i></div>
                        <span class="ph-title">Rate the Midman</span>
                    </div>
                    <div class="panel-body">
                        <form method="POST">
                            <div style="margin-bottom:22px;">
                                <div class="star-label">Your Rating <span style="color:var(--red);">*</span></div>
                                <div class="stars-input" id="midmanStars">
                                    <input type="radio" name="rating" id="ms5" value="5" required><label for="ms5">★</label>
                                    <input type="radio" name="rating" id="ms4" value="4"><label for="ms4">★</label>
                                    <input type="radio" name="rating" id="ms3" value="3"><label for="ms3">★</label>
                                    <input type="radio" name="rating" id="ms2" value="2"><label for="ms2">★</label>
                                    <input type="radio" name="rating" id="ms1" value="1"><label for="ms1">★</label>
                                </div>
                                <div class="rating-hint" id="ratingHint"></div>
                            </div>
                            <div style="margin-bottom:6px;">
                                <label class="form-label" for="review">Review <span style="color:var(--text-dim);font-weight:400;">(optional)</span></label>
                                <textarea id="review" name="review" class="form-textarea" placeholder="How was your experience with this midman? Describe their professionalism, communication, and reliability…"></textarea>
                            </div>
                            <div class="btn-row">
                                <button type="submit" class="btn btn-gold"><i class="fas fa-star"></i> Submit Rating</button>
                                <a href="<?php echo $back_link; ?>" class="btn btn-ghost">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ALL REVIEWS -->
            <?php if(mysqli_num_rows($all_ratings) > 0): ?>
            <div class="panel">
                <div class="panel-head">
                    <div class="ph-icon ph-gold"><i class="fas fa-comments"></i></div>
                    <span class="ph-title">All Ratings for This Transaction</span>
                </div>
                <div class="panel-body">
                    <?php while($r = mysqli_fetch_assoc($all_ratings)): ?>
                    <div class="review-item">
                        <div class="rev-top">
                            <div class="rev-ava"><?php echo strtoupper(substr($r['rater_name'],0,2)); ?></div>
                            <div>
                                <span class="rev-name"><?php echo htmlspecialchars($r['rater_name']); ?></span>
                                <span class="rev-role"><?php echo ucfirst($r['role']); ?></span>
                            </div>
                            <div class="rev-stars">
                                <?php for($i=1;$i<=5;$i++): ?>
                                    <i class="fas fa-star" style="color:<?php echo $i<=$r['rating']?'var(--gold)':'var(--text-dim)';?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php if($r['review']): ?>
                            <div class="rev-text">"<?php echo htmlspecialchars($r['review']); ?>"</div>
                        <?php endif; ?>
                        <div class="rev-date"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></div>
                    </div>
                    <?php endwhile; ?>
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

    const hints = ['', 'Terrible', 'Poor', 'Okay', 'Good', 'Excellent'];
    const starInputs = document.querySelectorAll('#midmanStars input');
    const hintDiv = document.getElementById('ratingHint');
    starInputs.forEach(inp => {
        inp.addEventListener('change', () => {
            const val = parseInt(inp.value);
            hintDiv.textContent = hints[val] || '';
            const colors = ['', 'var(--red)', '#ff9632', 'var(--gold)', 'var(--gold)', 'var(--teal)'];
            hintDiv.style.color = colors[val] || 'var(--text-dim)';
        });
    });
</script>
</body>
</html>