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
if($_SESSION['role'] != 'seller') { header('Location: buyer-dashboard.php'); exit(); }

$error   = '';
$success = '';

$upload_dir = 'uploads/';
if(!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

$games = ['League of Legends','Valorant','Mobile Legends','PUBG Mobile','Call of Duty Mobile','Genshin Impact','Dota 2','CS:GO','Fortnite','Apex Legends','Roblox','Minecraft','FIFA Mobile','Other'];

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title         = mysqli_real_escape_string($conn, $_POST['title']);
    $description   = mysqli_real_escape_string($conn, $_POST['description']);
    $price         = mysqli_real_escape_string($conn, $_POST['price']);
    $game_name     = mysqli_real_escape_string($conn, $_POST['game_name']);
    $item_type     = mysqli_real_escape_string($conn, $_POST['item_type']);
    $account_level = !empty($_POST['account_level']) ? intval($_POST['account_level']) : 'NULL';
    $account_rank  = mysqli_real_escape_string($conn, $_POST['account_rank']);
    $server_region = mysqli_real_escape_string($conn, $_POST['server_region']);
    $seller_id     = $_SESSION['user_id'];

    if(empty($title)||empty($description)||empty($price)||empty($game_name)||empty($item_type)) {
        $error = 'Please fill in all required fields.';
    } elseif(!is_numeric($price)||$price<=0) {
        $error = 'Please enter a valid price.';
    } else {
        $image_path = '';
        if(isset($_FILES['image']) && $_FILES['image']['error']==0) {
            $allowed = ['jpg','jpeg','png','gif'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if(in_array($ext,$allowed)) {
                $new_filename = time().'_'.uniqid().'.'.$ext;
                $target = $upload_dir.$new_filename;
                if(move_uploaded_file($_FILES['image']['tmp_name'],$target)) $image_path=$target;
                else $error='Failed to upload image.';
            } else { $error='Only JPG, PNG, and GIF files are allowed.'; }
        }
        if(empty($error)) {
            if($account_level=='NULL') {
                $q = "INSERT INTO products (seller_id,title,description,price,game_name,item_type,account_rank,server_region,image_path,category)
                      VALUES ($seller_id,'$title','$description',$price,'$game_name','$item_type','$account_rank','$server_region','$image_path','gaming')";
            } else {
                $q = "INSERT INTO products (seller_id,title,description,price,game_name,item_type,account_level,account_rank,server_region,image_path,category)
                      VALUES ($seller_id,'$title','$description',$price,'$game_name','$item_type',$account_level,'$account_rank','$server_region','$image_path','gaming')";
            }
            if(mysqli_query($conn,$q)) { $success='Gaming item listed successfully!'; $_POST=[]; }
            else $error='Failed to list item: '.mysqli_error($conn);
        }
    }
}

$item_types = [
    'account'  => ['icon'=>'fa-gamepad',  'label'=>'Game Account'],
    'currency' => ['icon'=>'fa-coins',    'label'=>'In-game Currency'],
    'item'     => ['icon'=>'fa-box-open', 'label'=>'Item / Skin'],
    'service'  => ['icon'=>'fa-bolt',     'label'=>'Boosting Service'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Gaming Product — Trusted Midman</title>
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
        .nav-badge { margin-left:auto; background:var(--red-dim); color:var(--red); font-size:0.6rem; font-weight:800; padding:2px 7px; border-radius:10px; border:1px solid rgba(255,77,109,0.15); }
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

        /* ── LAYOUT ── */
        .form-layout { display:grid; grid-template-columns:1fr 380px; gap:22px; align-items:start; }

        /* ── ALERTS ── */
        .alert { display:flex; align-items:center; gap:10px; padding:13px 16px; border-radius:var(--radius-sm); font-size:0.875rem; margin-bottom:20px; }
        .alert-success { background:var(--teal-dim); border:1px solid rgba(0,212,170,0.22); color:var(--teal); }
        .alert-error   { background:var(--red-dim);  border:1px solid rgba(255,77,109,0.22); color:#ff7090; }

        /* ── PANELS ── */
        .panel { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; opacity:0; animation:fadeUp 0.45s ease forwards; position:relative; }
        .panel::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(240,165,0,0.15),transparent); z-index:1; }
        .panel:nth-child(1){animation-delay:.05s;} .panel:nth-child(2){animation-delay:.12s;} .panel:nth-child(3){animation-delay:.19s;}

        .panel-head { padding:16px 22px; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:10px; }
        .panel-head-icon { width:32px; height:32px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:0.82rem; border:1px solid transparent; }
        .phi-gold   { background:var(--gold-dim);   color:var(--gold);   border-color:rgba(240,165,0,0.14); }
        .phi-teal   { background:var(--teal-dim);   color:var(--teal);   border-color:rgba(0,212,170,0.14); }
        .phi-blue   { background:var(--blue-dim);   color:var(--blue);   border-color:rgba(78,159,255,0.14); }
        .phi-purple { background:var(--purple-dim); color:var(--purple); border-color:rgba(160,100,255,0.14); }
        .panel-head-title { font-family:var(--font-head); font-size:0.95rem; font-weight:700; color:var(--text); letter-spacing:-0.01em; }
        .panel-body { padding:22px; }

        /* ── FORM FIELDS ── */
        .field { margin-bottom:18px; }
        .field:last-child { margin-bottom:0; }
        .field-label { display:block; font-size:0.75rem; font-weight:700; color:var(--text-muted); margin-bottom:7px; letter-spacing:0.06em; text-transform:uppercase; }
        .field-label .req { color:var(--red); margin-left:2px; }

        .field-input,
        .field-select,
        .field-textarea {
            width:100%; padding:11px 14px;
            background:var(--surface2); border:1px solid var(--border);
            border-radius:var(--radius-sm); color:var(--text-warm);
            font-family:var(--font-body); font-size:0.9rem;
            transition:all 0.22s; outline:none;
        }
        .field-input::placeholder,
        .field-textarea::placeholder { color:var(--text-dim); }
        .field-input:focus,
        .field-select:focus,
        .field-textarea:focus {
            border-color:var(--gold);
            box-shadow:0 0 0 3px rgba(240,165,0,0.1);
            background:var(--surface3);
        }
        .field-select {
            cursor:pointer; appearance:none;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23a89880' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat:no-repeat; background-position:right 13px center;
        }
        .field-select option { background:#201a13; }
        .field-textarea { resize:vertical; min-height:115px; line-height:1.65; }
        .field-hint { font-size:0.72rem; color:var(--text-dim); margin-top:5px; }
        .field-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }

        /* ── TYPE CARDS ── */
        .type-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
        .type-card { position:relative; }
        .type-card input { position:absolute; opacity:0; width:0; }
        .type-label {
            display:flex; align-items:center; gap:10px;
            padding:11px 13px;
            background:var(--surface2); border:1px solid var(--border);
            border-radius:var(--radius-sm); cursor:pointer;
            transition:all 0.22s; font-size:0.84rem; font-weight:500; color:var(--text-muted);
        }
        .type-label:hover { border-color:var(--border2); color:var(--text-warm); background:var(--surface3); }
        .type-icon { width:28px; height:28px; border-radius:7px; background:var(--surface); display:flex; align-items:center; justify-content:center; font-size:0.75rem; transition:all 0.22s; flex-shrink:0; border:1px solid var(--border); }
        .type-card input:checked + .type-label { border-color:var(--gold); background:rgba(240,165,0,0.08); color:var(--gold); }
        .type-card input:checked + .type-label .type-icon { background:var(--gold); color:#0f0c08; border-color:var(--gold); }

        /* ── FILE UPLOAD ── */
        .upload-zone {
            border:2px dashed var(--border2); border-radius:var(--radius-sm);
            padding:28px 20px; text-align:center; cursor:pointer;
            transition:all 0.22s; position:relative;
        }
        .upload-zone:hover, .upload-zone.dragover { border-color:var(--gold); background:var(--gold-dim); }
        .upload-zone input { position:absolute; inset:0; opacity:0; cursor:pointer; }
        .upload-icon { font-size:1.8rem; color:var(--text-dim); margin-bottom:8px; }
        .upload-text { font-size:0.84rem; color:var(--text-muted); }
        .upload-hint { font-size:0.72rem; color:var(--text-dim); margin-top:4px; }
        #previewWrap { display:none; margin-top:12px; }
        #previewWrap img { width:100%; max-height:160px; object-fit:cover; border-radius:var(--radius-sm); border:1px solid var(--border2); }

        /* ── PREVIEW CARD ── */
        .preview-card {
            background:var(--surface2); border:1px solid var(--border);
            border-radius:var(--radius-sm); padding:16px; margin-bottom:16px;
        }
        .preview-title { font-family:var(--font-head); font-size:1.05rem; font-weight:700; color:var(--text); margin-bottom:5px; min-height:24px; letter-spacing:-0.01em; }
        .preview-game  { font-size:0.7rem; font-weight:700; background:var(--gold-dim); color:var(--gold); padding:2px 9px; border-radius:10px; display:inline-block; margin-bottom:9px; border:1px solid rgba(240,165,0,0.15); }
        .preview-price { font-family:var(--font-head); font-size:1.5rem; font-weight:800; color:var(--gold); letter-spacing:-0.01em; }
        .preview-type  { font-size:0.72rem; color:var(--text-dim); margin-top:4px; }

        /* ── TRUST STRIP ── */
        .trust-strip { margin-top:14px; padding:12px 14px; background:var(--surface2); border-radius:var(--radius-sm); border:1px solid var(--border); }
        .trust-item { display:flex; align-items:center; gap:8px; font-size:0.77rem; color:var(--text-muted); padding:4px 0; }
        .trust-item i { color:var(--teal); font-size:0.7rem; width:14px; }

        /* ── BUTTONS ── */
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:7px; padding:11px 18px; border-radius:var(--radius-sm); font-family:var(--font-body); font-size:0.9rem; font-weight:600; text-decoration:none; cursor:pointer; border:none; transition:all 0.22s ease; white-space:nowrap; letter-spacing:0.01em; }
        .btn-gold { background:linear-gradient(135deg,var(--gold),#d48500); color:#0f0c08; font-weight:700; box-shadow:0 4px 16px var(--gold-glow), 0 1px 0 rgba(255,255,255,0.1) inset; width:100%; }
        .btn-gold:hover { background:linear-gradient(135deg,var(--gold-lt),var(--gold)); transform:translateY(-2px); box-shadow:0 8px 24px rgba(240,165,0,0.4); }
        .btn-ghost { background:var(--surface2); color:var(--text-muted); border:1px solid var(--border2); width:100%; margin-top:8px; }
        .btn-ghost:hover { color:var(--text-warm); border-color:var(--border3); }
        .btn-sm { padding:7px 13px; font-size:0.82rem; width:auto; }

        /* ── MISC ── */
        .sidebar-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:99; backdrop-filter:blur(4px); }
        .sidebar-overlay.visible { display:block; }
        ::-webkit-scrollbar { width:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }

        @media(max-width:1000px) { .form-layout{grid-template-columns:1fr;} }
        @media(max-width:820px)  { .sidebar{transform:translateX(-100%);} .sidebar.open{transform:translateX(0);} .main{margin-left:0;} .hamburger{display:flex;} .content{padding:20px 16px;} }
        @media(max-width:540px)  { .field-row{grid-template-columns:1fr;} .type-grid{grid-template-columns:1fr 1fr;} }
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
            <a href="add-product.php"      class="nav-link active"><span class="nav-icon"><i class="fas fa-plus-circle"></i></span> Add Product</a>
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
                <span class="page-title">Add Gaming Product</span>
            </div>
            <a href="my-products.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> My Products</a>
        </header>

        <div class="content">

            <?php if($success): ?>
                <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?php echo $success; ?></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-error"><i class="fas fa-triangle-exclamation"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
            <div class="form-layout">

                <!-- LEFT: FORM FIELDS -->
                <div style="display:flex;flex-direction:column;gap:18px;">

                    <!-- BASIC INFO -->
                    <div class="panel">
                        <div class="panel-head">
                            <div class="panel-head-icon phi-gold"><i class="fas fa-gamepad"></i></div>
                            <div class="panel-head-title">Basic Information</div>
                        </div>
                        <div class="panel-body">
                            <div class="field">
                                <label class="field-label">Game <span class="req">*</span></label>
                                <select name="game_name" class="field-select" id="selGame" required>
                                    <option value="">Select a game…</option>
                                    <?php foreach($games as $g): ?>
                                    <option value="<?php echo $g; ?>" <?php echo (isset($_POST['game_name'])&&$_POST['game_name']==$g)?'selected':''; ?>><?php echo $g; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="field">
                                <label class="field-label">Item Type <span class="req">*</span></label>
                                <div class="type-grid">
                                    <?php foreach($item_types as $val=>$t):
                                        $checked = (isset($_POST['item_type'])&&$_POST['item_type']==$val)?'checked':'';
                                    ?>
                                    <div class="type-card">
                                        <input type="radio" name="item_type" value="<?php echo $val; ?>" id="type_<?php echo $val; ?>" <?php echo $checked; ?> required>
                                        <label class="type-label" for="type_<?php echo $val; ?>">
                                            <div class="type-icon"><i class="fas <?php echo $t['icon']; ?>"></i></div>
                                            <?php echo $t['label']; ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="field">
                                <label class="field-label" for="title">Listing Title <span class="req">*</span></label>
                                <input type="text" name="title" id="title" class="field-input"
                                       value="<?php echo htmlspecialchars($_POST['title']??''); ?>"
                                       placeholder="e.g., Diamond Valorant Account — NA Server" required>
                            </div>

                            <div class="field">
                                <label class="field-label" for="desc">Description <span class="req">*</span></label>
                                <textarea name="description" id="desc" class="field-textarea"
                                          placeholder="Describe the item in detail — rank, skins, champions, history…" required><?php echo htmlspecialchars($_POST['description']??''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- ACCOUNT DETAILS -->
                    <!-- ACCOUNT DETAILS -->
                    <div class="panel" id="accountDetailsPanel" style="display:none;">
                        <div class="panel-head">
                            <div class="panel-head-icon phi-blue"><i class="fas fa-sliders"></i></div>
                            <div class="panel-head-title">Account Details <span style="font-weight:400;color:var(--text-dim);font-size:0.78rem;">(optional)</span></div>
                        </div>
                        <div class="panel-body">
                            <div class="field-row">
                                <div class="field">
                                    <label class="field-label">Account Level</label>
                                    <input type="number" name="account_level" class="field-input"
                                           value="<?php echo htmlspecialchars($_POST['account_level']??''); ?>"
                                           placeholder="e.g., 30">
                                </div>
                                <div class="field">
                                    <label class="field-label">Rank</label>
                                    <input type="text" name="account_rank" class="field-input"
                                           value="<?php echo htmlspecialchars($_POST['account_rank']??''); ?>"
                                           placeholder="e.g., Diamond, Legend">
                                </div>
                            </div>
                            <div class="field">
                                <label class="field-label">Server / Region</label>
                                <input type="text" name="server_region" class="field-input"
                                       value="<?php echo htmlspecialchars($_POST['server_region']??''); ?>"
                                       placeholder="e.g., NA, EU, SEA, Asia">
                            </div>
                        </div>
                    </div>

                    <!-- PRICING -->
                    <div class="panel">
                        <div class="panel-head">
                            <div class="panel-head-icon phi-teal"><i class="fas fa-tag"></i></div>
                            <div class="panel-head-title">Pricing</div>
                        </div>
                        <div class="panel-body">
                            <div class="field">
                                <label class="field-label">Price (USD) <span class="req">*</span></label>
                                <div style="position:relative;">
                                    <span style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--gold);font-family:var(--font-head);font-weight:700;font-size:1rem;">$</span>
                                    <input type="number" name="price" id="priceInput" class="field-input"
                                           style="padding-left:26px;" step="0.01" min="0.01"
                                           value="<?php echo htmlspecialchars($_POST['price']??''); ?>"
                                           placeholder="0.00" required>
                                </div>
                                <div class="field-hint">A 5% service fee will be applied at checkout for midman protection.</div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- RIGHT: SIDEBAR PANELS -->
                <div style="display:flex;flex-direction:column;gap:18px;">

                    <!-- IMAGE UPLOAD -->
                    <div class="panel">
                        <div class="panel-head">
                            <div class="panel-head-icon phi-purple"><i class="fas fa-image"></i></div>
                            <div class="panel-head-title">Product Image</div>
                        </div>
                        <div class="panel-body">
                            <div class="upload-zone" id="uploadZone">
                                <input type="file" name="image" accept="image/*" id="imgInput">
                                <div class="upload-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                                <div class="upload-text">Click or drag to upload</div>
                                <div class="upload-hint">JPG, PNG, GIF — max 2MB</div>
                            </div>
                            <div id="previewWrap">
                                <img id="previewImg" src="" alt="Preview">
                            </div>
                        </div>
                    </div>

                    <!-- LIVE PREVIEW -->
                    <div class="panel">
                        <div class="panel-head">
                            <div class="panel-head-icon phi-gold"><i class="fas fa-eye"></i></div>
                            <div class="panel-head-title">Listing Preview</div>
                        </div>
                        <div class="panel-body">
                            <div class="preview-card">
                                <div class="preview-title" id="prevTitle">Your listing title…</div>
                                <div class="preview-game" id="prevGame" style="display:none;"></div>
                                <div class="preview-price" id="prevPrice">$—</div>
                                <div class="preview-type"  id="prevType"></div>
                            </div>
                            <div class="trust-strip">
                                <div class="trust-item"><i class="fas fa-shield-halved"></i> Midman-protected transaction</div>
                                <div class="trust-item"><i class="fas fa-rotate-left"></i> Dispute resolution available</div>
                                <div class="trust-item"><i class="fas fa-star"></i> Rating after completion</div>
                            </div>
                        </div>
                    </div>

                    <!-- SUBMIT -->
                    <div>
                        <button type="submit" class="btn btn-gold"><i class="fas fa-cloud-arrow-up"></i> List Gaming Item</button>
                        <a href="seller-dashboard.php" class="btn btn-ghost"><i class="fas fa-xmark"></i> Cancel</a>
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

    // Image preview
    document.getElementById('imgInput').addEventListener('change', function() {
        if(this.files && this.files[0]) {
            const r = new FileReader();
            r.onload = e => {
                document.getElementById('previewImg').src = e.target.result;
                document.getElementById('previewWrap').style.display = 'block';
            };
            r.readAsDataURL(this.files[0]);
        }
    });

    // Drag over
    const uz = document.getElementById('uploadZone');
    uz.addEventListener('dragover',  e => { e.preventDefault(); uz.classList.add('dragover'); });
    uz.addEventListener('dragleave', () => uz.classList.remove('dragover'));
    uz.addEventListener('drop',      e => { e.preventDefault(); uz.classList.remove('dragover'); });

    // Live preview
    const titleEl = document.getElementById('title');
    const priceEl = document.getElementById('priceInput');
    const gameEl  = document.getElementById('selGame');

    function updatePreview() {
        const t = titleEl.value.trim();
        document.getElementById('prevTitle').textContent = t || 'Your listing title…';
        const p = parseFloat(priceEl.value);
        document.getElementById('prevPrice').textContent = isNaN(p) ? '$—' : '$' + p.toFixed(2);
        const g  = gameEl.value;
        const pg = document.getElementById('prevGame');
        if(g) { pg.style.display = 'inline-block'; pg.textContent = g; } else { pg.style.display = 'none'; }
        const checked = document.querySelector('input[name="item_type"]:checked');
        document.getElementById('prevType').textContent = checked ? checked.nextElementSibling.textContent.trim() : '';
    }

    titleEl.addEventListener('input',  updatePreview);
    priceEl.addEventListener('input',  updatePreview);
    gameEl.addEventListener('change',  updatePreview);
  document.querySelectorAll('input[name="item_type"]').forEach(r => {
    r.addEventListener('change', updatePreview);
    r.addEventListener('change', toggleAccountDetails);
});
updatePreview();
toggleAccountDetails();

function toggleAccountDetails() {
    const checked = document.querySelector('input[name="item_type"]:checked');
    const panel   = document.getElementById('accountDetailsPanel');
    if(panel) panel.style.display = (checked && checked.value === 'account') ? 'block' : 'none';
}
</script>
</body>
</html>